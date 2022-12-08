<?php
namespace Lolaji\LaravelControllerTrait;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Validation\Validator;


trait LaravelControllerTrait 
{
    private $__sorts = ['latest', 'oldest', 'inRandomOrder', 'reorder', 'orderBy'];

    private $__response = [];

    private $__validate_rules = [];
    private $__validate_message = [];

    protected $_run_form_validation = true; //Determin whether to run form validation

    protected $_return_data_in_enter = false;

    public function upsert (Request $request, $id = null) 
    {
        $response = ['success'=>false, 'data'=>null, 'message'=>[]];

        // Run $this->_enter() method if exist
        if (method_exists($this, '_enter')) {
            $enter = $this->_enter('upsert', $request, $id);
            if ($this->_return_data_in_enter) {
                return $enter;
            }
        }

        if ($this->_run_form_validation) {
            if ($this->__validate($request)){
                return $this->getResponse();
            }
        }

        $cred = $this->__getRequestData($request);

        $model = $this->_model;
        $instance = new $model();
        $operation = "create";
        if (!is_null($id)){
            $operation = "update";
            $instance = $instance->findOrFail(intval($id));
        }

        // Run $this->_authorize() method if defined in controller
        // and return result if not null
        $auth_return = $this->__callAuthorize($request, $operation, $instance);
        if (!is_null($auth_return)) {
            return $auth_return;
        }
        

        $this->__callHook($request, $id, "before{$operation}d");
        
        if ($data = $instance->$operation($cred)) {
            $data = ($operation == 'create')? $data : $model::find($id);
            $call_hook_return_data = $this->__callHook($request, $data, "{$operation}d");

            $response['success'] = true;
            $response['operation'] = $operation;
            // if (isset($this->_send_data_with_request_response) && ($this->_send_data_with_request_response == true)) {
                $response['data'] = $data;
            // }
            $response['message'] = "Sucessfully {$operation}d";

            // append data return from $this->_hook() method in the controller
            // to $response variable if not null
            if (! is_null($call_hook_return_data))
                $response['hook'] = $call_hook_return_data;
        } else {
            $response['success'] = false;
            $response['message'] = "Unable to {$operation} due to system error. Please try again.";
        }
        return $response;
    }

    public function upsertByForeign (Request $request, $id, $relationModel, $relationModelId=null)
    {
        $response = ['success'=>false, 'message'=>[]];

        // Run $this->_before_validate() method if exist
        if (method_exists($this, '_enter')) {
            $enter = $this->_enter('upsertByForeign', $request, $id, $relationModel, $relationModelId);
            if ($this->_return_data_in_enter) {
                return $enter;
            }
        }

        if ($this->_run_form_validation) {
            if ($this->__validate($request, $relationModel)){
                return $this->getResponse();
            }
        }

        $cred = $this->__getRequestData($request, true, $relationModel);

        $parentModel = $this->_model::findOrFail(intval($id));
        $modelObj = $parentModel->$relationModel();
        $operation = 'create';
        $childModel = null;

        if (!is_null($relationModelId)) {
            $operation = 'update';
            $childModel = $modelObj->findOrFail(intval($relationModelId));
        } else {
            if (isset($parentModel->$relationModel->id)) {
                $operation = 'update';
            }
        }

        // Run $this->_{relationship_method}_authorize() method if defined in controller
        // and return result if not null
        $this->__callAuthorize($request, $operation, $childModel, $relationModel);
        
        if ($data = $modelObj->$operation($cred)) {
            $data = ($operation == 'create')? $data : $parentModel->$relationModel()->find($relationModelId);
            $call_hook_return_data = $this->__callHook($request, $data, "{$operation}d", true, $relationModel);
            $response['success'] = true;
            $response['data'] = $data;
            $response['operation'] = $operation;
            $response['message'] = ucfirst($relationModel)." {$operation}d successfully.";

            // append data return from $this->_hook() method in the controller
            // to $response variable if not null
            if (! is_null($call_hook_return_data))
                $response['hook'] = $call_hook_return_data;
        } else {
            $response['success'] = true;
            $response['message'] = "Unable to $operation $relationModel due to system error. Please try again.";
        }

        return $response;
    }

    public function deattach (Request $request, $id, $relationship)
    {
        $response = ['success' => false, 'message'=>''];
        $operations = ['detach', 'attach', 'sync', 'syncWithoutDetaching'];

        // form input
        $operation = $request->post('operation');
        $value = $request->post('value');

        if (in_array($operation, $operations)) {
            $model = $this->_model;
            $instance = (new $model())->findOrFail($id);

            $is_reach_max_attach_limit = $this->__reachedMaxAttachLimit($instance, $relationship);

            if ($value && (!empty($value) || !is_null($value))) {
                if (!$is_reach_max_attach_limit) {
                    if ($data = $instance->$relationship()->$operation($value)) {
                        
                        $call_hook_return_data = $this->__callHook(
                            $request, 
                            ['instance'=>$instance, 'relationship'=> $relationship, 'data'=>$data], 
                            "{$operation}ed"
                        );

                        $response['success'] = true;
                        $response['operation'] = $operation;
                        $response['data'] = $data;
                        $response['message'] = "{$operation}ed";

                        // append data return from $this->_hook() method in the controller
                        // to $response variable if not null or empty
                        if (! is_null($call_hook_return_data) && !empty($call_hook_return_data))
                            $response['hook'] = $call_hook_return_data;
                    } else {
                        $response['success'] = false;
                        $response['message'] = "Unable to {$operation} due to system error.";
                    }
                } else {
                    $response['success'] = false;
                    $response['message'] = "reached-maximum-attach-limit";
                }
            } else {
                $response['success'] = false;
                $response['message'] = 'empty-value';
            }
        } else {
            $response['success'] = false;
            $response['message'] = 'invalid-operation-method';
        }
        return $response;
    }

    public function get (Request $request, $id, $relationModel=null)
    {
        $modelObj = $this->_model::findOrFail($id);
        $loadModel = $request->get('load_models');
        $where = $request->get('where');
        $call = $request->get('call');

        if (!is_null($relationModel)) {
            if ($loadModel){
                return $modelObj->$relationModel->load($loadModel);
            }
            $modelObj = $modelObj->$relationModel;
        } else {
            if ($loadModel) {
                $modelObj->load($loadModel);
            }
        }

        
        if ($where) {
            
            $where_decode = json_decode($where);
            if (is_array($where) && $this->_isMulti($where) && !$this->_hasEmptyMulti($where)) {
                $modelObj = $modelObj->where($where);
            }
        }

        //call the $this->_authorize() method if defined in the controller.
        //and return the data if not null
        if (method_exists($this, '_authorize')) {
            $auth_return = $this->_authorize('get', $request, 'get', $modelObj);
            if (!is_null($auth_return)) {
                return $auth_return;
            }
        }

        if ($call) {
            return $modelObj->$call();
        }
        
        return $modelObj;
    }

    public function fetch (Request $request)
    {
        $returnCount = $request->get('count', false);
        $sort = $request->get('sort');
        $where = $request->get('where');
        $fields = $request->get('fields', []);
        $relationship = $request->get('relationship', []);
        $limit = $request->get('limit');
        $offset = $request->get('offset');
        $pagination = $request->get('pagination', []);
        $page = $request->get('page', false);

        $model = $this->_model;
        $instance = new $model();

        if ($where) {
            $where_decode = json_decode($where);
            if (is_array($where_decode) && $this->_isMulti($where_decode) && !$this->_hasEmptyMulti($where_decode)) {
                $instance = $instance->where($where_decode);
            }
        }

        if (is_array($relationship) && !empty($relationship)) {
            $instance->with($relationship);
        }

        if ($sort) {
            if (is_array($sort) && (count($sort) == 2)) {
                $instance = $instance->orderBy($sort[0], $sort[1]);
            } elseif (is_string($sort)) {
                $instance = $instance->latest($sort);
            }
        }
        
        if (is_numeric($limit)) {
            $instance->limit($limit);
        }

        if (is_numeric($offset)) {
            $instance->offset($limit);
        }

        if ($returnCount) {
            $results = $instance->count();
        } else {
            if (!empty($pagination) && $page) {
                $results = $instance->paginate($pagination->per_page ?? 10);

                if (isset ($pagination->path) && !is_null($pagination->path)) {
                    $results->withPath($pagination->path);
                }

                if (isset($pagination->appends) && !empty($pagination->appends)) {
                    $results->appends($pagination->appends);
                }

            } else if (!empty($fields)) {
                $results = $instance->get($fields);
            } else {
                $results = $instance->get();
            }
        }

        //call the $this->_authorize() method if defined in the controller.
        //and return the data if not null
        if (method_exists($this, '_authorize')) {
            $auth_return = $this->_authorize('fetch', $request, 'fetch', $results);
            if (!is_null($auth_return)) {
                return $auth_return;
            }
        }

        return $results;
    }
    
    public function destroy (Request $request, $id)
    {
        $operation = 'delete';
        // Run $this->_enter() method if exist
        if (method_exists($this, '_enter')){
            $enter = $this->_enter('destroy', $request, $id);
            if ($this->_return_data_in_enter) {
                return $enter;
            }
        }

        // check ',' is in $id parameter
        // if found, expload $id to array
        if (stripos($id, ',')) {
            $operation = 'deleteMany';
            $id = explode(',', $id);
        }
        
        $bd_hook = $this->__callHook($request, $id, 'beforeDestroyed');

        if (isset($bd_hook['force']) && $bd_hook['force']) {
            return $bd_hook;
        }

        // Run $this->_authorize() method if defined in controller
        // and return result if not null
        $auth_return = $this->__callAuthorize($request, $operation, $id);
        if (!is_null($auth_return)) {
            return $auth_return;
        }
        
        if ( $this->_model::destroy($id)) {
            $this->__callHook($request, $id, 'destroyed');
            return 'true';
        }
        return 'false';
    }

    public function setValidateRules($validate_rules = [], $validate_message = [])
    {
        $this->__validate_message = $validate_message;
        $this->__validate_rules = $validate_rules;
    }

    private function __validate (Request $request, $relationModelName=null)
    {
        $response = ['success'=>false, 'message'=>[]];

        $validatation = function($validate_rules, $validate_message) use ($request, $response) {
            $validate = Validator::make($request->all(), $validate_rules, $validate_message);
            if ($validate->fails()) {
                $response['success'] = false;
                foreach(Arr::dot($request->all()) as $key => $value) {
                    $response['message'][$key] = $validate->errors()->first($key);
                }
                $this->setResponse($response);
                return true;
            }
            return false;
        };

        if (! empty($this->__validate_rules)) {
            // return $this->__validate_message;
            return $validatation($this->__validate_rules, $this->__validate_message);
        } else {
            $validate_message = [];
            // check if _validate_rules method exist
            // and do the validation 
            $method = '_validate_rules';
            $message_method = '_validate_message';
            if (!is_null($relationModelName) && !empty($relationModelName)) {
                $method = "_validate_{$relationModelName}_rules";
                $message_method = "_validate_{$relationModelName}_message";
            }
            
            if ((int) method_exists($this, $method)){

                if ((int) method_exists($this, $message_method)) {
                    $validate_message = $this->$message_method();
                }
                
                return $validatation($this->$method(), $validate_message);
            }
        }
    }

    private function __getRequestData(Request $request, $is_relational=false, $relationModelName="")
    {
        $property = ($is_relational)? "_{$relationModelName}_fillable" : "_fillable";
        
        if (isset($this->$property) && !empty($this->$property)) {
            return $request->only($this->$property);
        }
        return $request->all();
    }

    /**
     * Call protected $this->_hook() method
     * defined in controller after
     * model created, updated or deleted successfully
     * 
     * @param $request
     * @param $model model
     * @param $flag database operation executed
     * @param $is_relational determine if the model perform database operation on is a relational model
     * @param $relationModelName determine the relationship method name of the model perform database operation
     * 
     * @return void
     */
    private function __callHook (Request $request, $model, $flag='created', $is_relational=false, $relationModelName="")
    {
        $method = ($is_relational)? "_{$relationModelName}_hook" : "_hook";
        if ( (int) method_exists($this, $method) ) {
            return $this->$method($request, $model, $flag);
        }
    }

    /**
     * Call the $this->_{$relationship}_autorize() or $this->_authorize() method(s) define in the controller if exist
     * 
     * @param $request controller request object
     * @param $operation operation (create, update, delete etc) to be perform
     * @param $operation operation (create, update, delete etc) to be performed
     * @param $model model
     * @param $relationship determine the model relationship
     * 
     * @return 
     */
    private function __callAuthorize (Request $request, $operation, $model, $relationship=null) 
    {
        $method = (!is_null($relationship))? "_{$relationship}_authorize" : '_authorize';
        if (method_exists($this, $method)) {
            return $this->$method($request, $operation, $model);
        }
    }

    public function setResponse ($response) 
    {
        $this->__response = $response;
    }

    public function getResponse()
    {
        return $this->__response;
    }

    /**
     * validate the maximum attach limit 
     * if the property $this->_{$relationship}_attach_limit of $relationship isset
     * in the controller
     * 
     * 
     * @return bool true if it reach the max attach limit set in the controller
     */
    private function __reachedMaxAttachLimit($instance, $relationship)
    {
        $property = "_{$relationship}_attach_limit";
        if (isset($this->$property)) {
            return count($instance->$relationship) == $this->$property;
        }
        return false;
    }

    protected function _isMulti (array $array)
    {
        $inner = array_filter($array,'is_array');
        if(count($inner)>0) return true;
        return false;
    }

    protected function _hasEmptyMulti (array $array)
    {
        $inner = array_filter($array, function ($inner) {
            return empty($inner);
        });
        if (count($inner) > 0) return true;
        return false;
    }

    public function ignoreFormValidation()
    {
        return $this->_run_form_validation = false;
    }

    public function returnDataInEnterHook($bool=true) 
    {
        $this->_return_data_in_enter = $bool;
    }
}