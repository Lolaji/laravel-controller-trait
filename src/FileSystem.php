<?php

namespace Lolaji\LaravelControllerTrait;

use Facade\FlareClient\Stacktrace\File;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

trait FileSystem 
{
    private $__disk = null;
    private $__path = '';
    private $__path_visible=false;

    protected $_auto_generate_filename = false;
    protected $_fs_multiple = false;
    
    public function fsUpload (Request $request)
    {
        $response = ['success'=>false, 'message'=>[]];

        if (method_exists($this, '_fs_before_hook')) {
            $this->_fs_before_hook($request, 'upload');
        }

        //run validate rules
        if (method_exists($this, '_validate_fs_rules')) {
            $validate_message = [];
            if (method_exists($this, '_validate_fs_message'))
                $validate_message = $this->_validate_fs_message();

            $validate = Validator::make(
                $request->all(),
                $this->_validate_fs_rules(),
                $validate_message
            );
            if ($validate->fails()) {
                $response['success'] = false;
                foreach($request->all() as $key => $value) {
                    $response['message'][$key] = $validate->errors()->first($key);
                }
                return $response;
            }
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            
            $dir = $this->__setFolder($this->__path);

            $fullUploadedPath = [];

            if ($this->_fs_multiple) {
                foreach($file as $f){
                    $fullUploadedPath[] = $this->_do_upload($f, $dir);
                }
            } else {
                $fullUploadedPath = $this->_do_upload($file, $dir);
            }
        
            $uploadPathUrl = $this->_getUploadPathUrl ($fullUploadedPath);
            if (method_exists($this, '_fs_hook')) {
                $response['hook'] = $this->_fs_hook($request, 'uploaded', [
                    'uploaded_path' => $fullUploadedPath, 
                    'uploaded_path_url' => $uploadPathUrl
                ]);
            }
            $response['success'] = true;
            $response['upload_path'] = $fullUploadedPath;
            $response['upload_path_url'] = $uploadPathUrl;
            $response['message'] = 'File uploaded.';

        } else {
            $response['success'] = false;
            $response['message']['file'] = 'No file selected';
        }

        return $response;
    }

    public function fsFetch(Request $request) 
    {
        if (method_exists($this, '_fs_before_hook')) {
            $this->_fs_before_hook($request, 'fetch');
        }
        
        $disk = $this->_getDisk();
        $diskInstance = Storage::disk($disk);

        return $diskInstance->files($this->__path);
    }

    public function fsDelete($path) 
    {
        $delete = Storage::disk($this->_getDisk())->delete($path);
    }

    protected function _do_upload ($file, $dir) 
    {
        $innerFolder = explode('/', $file->getMimeType())[0].'s';

        $addedPath = $dir.'/'.$innerFolder;

        $path = '';

        if ($this->_auto_generate_filename) {
            if ($this->__path_visible) {
                $path = $file->storePublicly($addedPath, $this->_getDisk());
            } else {
                $path = $file->store($addedPath, $this->_getDisk());
            }
        } else {
            $filename = $file->getClientOriginalName();
            if ($this->__path_visible) {
                $path = $file->storePubliclyAs($dir, $filename, $this->_getDisk());
            } else {
                $path = $file->storeAs($dir, $filename, $this->_getDisk());
            }
        }

        $fullUploadedPath = str_replace("public/", '', $path);

        return $fullUploadedPath;
    }

    protected function _getUploadPathUrl($uploadPath) 
    {
        $uploadPathUrl = [];

        if (is_array($uploadPath)) {
            $uploadPathUrl = Arr::map($uploadPath, function($url) {
                return Storage::url($url);
            });
        } else {
            $uploadPathUrl = Storage::url($uploadPath);
        }

        return $uploadPathUrl;
    }

    protected function _setPath($path)
    {
        return $this->__path = $path;
    }

    protected function _setDisk($disk)
    {
        $this->__disk = $disk;
    }

    protected function _getDisk()
    {
        return $this->__disk ?? config('filesystem.default');
    }

    protected function _setPathVisible($bool=false) 
    {
        $this->__path_visible = $bool;
    }

    private function __setFolder ($folder=null)
    {
        $dir = 'public';

        if (!is_null($folder)) {
            if (strpos($folder, ',') !== false){
                $folder = str_replace(',', '/', $folder);
                $dir = "public/$folder";
            } else {
                $dir = "public/$folder";
            }
        }

        return $dir;
    }

    public function autoGenerateFilename()
    {
        $this->_auto_generate_filename = true;
    }
}