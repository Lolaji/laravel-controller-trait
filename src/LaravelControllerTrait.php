<?php
namespace Lolaji\LaravelControllerTrait;

use Exception;
use Lolaji\LaravelControllerTrait\Helpers\Arr as HelpersArr;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lolaji\LaravelControllerTrait\Enum\DeattachMethodEnum;
use Lolaji\LaravelControllerTrait\Exceptions\RequestMethodException;
use Lolaji\LaravelControllerTrait\Helpers\Str;

trait LaravelControllerTrait 
{
    private $__sorts = ['latest', 'oldest', 'inRandomOrder', 'reorder', 'orderBy'];

    private $__response = [];

    private $__validate_rules = [];
    private $__validate_message = [];
    private $__hook_data = null;

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

        // return error if the $cred is empty.
        // This error occure due to _fillable property that has not 
        // been decleared in the controller, or decleared and empty.
        if (empty($cred)) {
            return response(
                content: "Not Allowed: Credential Error.",
                status: 405
            );
        }

        $model = $this->_model;
        $instance = new $model();
        $operation = "create";
        if (!is_null($id)){
            $operation = "update";
            $instance = $instance->findOrFail(intval($id));
        }

        // Run $this->_authorize() method if defined in controller
        // and return result if not null
        $auth_return = $this->__callAuthorize($operation, $instance);
        if (!is_null($auth_return)) {
            return $auth_return;
        }
        

        $this->__callHook($request, $id, "before{$operation}d");
        
        if ($data = $instance->$operation($cred)) {
            $data = ($operation == 'create')? $data : $model::find($id);
            $this->__callHook($request, $data, "{$operation}d");

            $response['success'] = true;
            $response['operation'] = $operation;
            $response['data'] = $this->__makeResource ("upsert", $data);
            $response['message'] = "Sucessfully {$operation}d";

            // append data return from $this->_hook() method in the controller
            // to $response variable if not null
            if (! is_null($this->__hook_data))
                $response['hook'] = $this->__hook_data;
        } else {
            $response['success'] = false;
            $response['message'] = "Unable to {$operation} due to system error. Please try again.";
        }
        return $response;
    }

    public function upsertByForeign (Request $request, $id, $relationModel, $relationModelId=null)
    {
        $parentModel = $this->_model::findOrFail(intval($id));

        $response = ['success'=>false, 'message'=>[]];

        // Run $this->_before_validate() method if exist
        if (method_exists($this, '_enter')) {
            $enter = $this->_enter('upsertByForeign', $request, $id, $relationModel, $relationModelId);
            if ($this->_return_data_in_enter) {
                return $enter;
            }
        }

        if ($this->_run_form_validation) {
            if ($this->__validate(
                request: $request, 
                relationModelName: $relationModel, 
                parentModel: $parentModel
            )) {
                return $this->getResponse();
            }
        }

        $cred = $this->__getRequestData($request, true, $relationModel);

        // return error if the $cred is empty.
        // This error occure due to _{relationship}_fillable property that has not 
        // been decleared in the parent controller, or decleared and empty.
        if (empty($cred)) {
            return response(
                content: "Not Allowed: Credential Error.",
                status: 405
            );
        }

        // Run $this->_authorize() method if defined in parent controller
        $this->__callAuthorize("get", $parentModel);

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
        $this->__callAuthorize($operation, $childModel, $relationModel);
        
        if ($data = $modelObj->$operation($cred)) {
            // $data = ($operation == 'create')? $data : $parentModel->$relationModel()->find($relationModelId);
            if ($operation != 'create') {
                if(isset($parentModel->$relationModel->id)) {
                    $data = $parentModel->$relationModel;
                } else {
                    $data = $parentModel->$relationModel()->find($relationModelId);
                }
            }

            $this->__callHook($request, $data, "{$operation}d", true, $relationModel);
            $response['success'] = true;
            $response['data'] = $this->__makeResource("upsert", $data, $relationModel);
            $response['operation'] = $operation;
            $response['message'] = ucfirst($relationModel)." {$operation}d successfully.";

            // append data return from $this->_hook() method in the controller
            // to $response variable if not null
            if (! is_null($this->__hook_data))
                $response['hook'] = $this->__hook_data;
        } else {
            $response['success'] = true;
            $response['message'] = "Unable to $operation $relationModel due to system error. Please try again.";
        }

        return $response;
    }

    public function deattach (Request $request, $id, $relationship, $relationship_id=null)
    {
        $response = ['success' => false, 'message'=>''];
        $operations = ['detach', 'attach', 'sync', 'syncWithoutDetaching', 'updateExistingPivot', 'syncWithPivotValues'];

        // form input
        $operation = $request->post('operation');
        $value = $request->post('value');

        if (in_array($operation, $operations)) {
            $model = $this->_model;
            $instance = (new $model())->findOrFail($id);

            $this->__callAuthorize($operation, $instance);

            $is_reach_max_attach_limit = $this->__reachedMaxAttachLimit($instance, $relationship);

            if ($value && (!empty($value) || !is_null($value))) {
                if (!$is_reach_max_attach_limit || !is_null($relationship_id)) {
                    $data = null;
                    $res = null;
                    if (in_array($relationship, $this->_getRelationModels())) {
                        if (!is_null($relationship_id)) {
                            $res = $instance->$relationship()->$operation($relationship_id, $value);
                        } else {
                            $res = $instance->$relationship()->$operation($value);
                        }
                    } else {
                        return RequestMethodException::response(
                            debug_message: "Deattach Method Error: \"{$relationship}\" model does not exist and/or is not decleared in the parent controller.",
                            debug_status: 409,
                            message: "Resource could not be found",
                            status: 404
                        );
                    }

                    $data = $this->_serializeDeattachReponse($operation, $value, $res);

                    // if ($data) {
                        
                        $this->__callHook(
                            $request, 
                            ['instance'=>$instance, 'relationship'=> $relationship, 'data'=>$data], 
                            "{$operation}"
                        );

                        $response['success'] = true;
                        $response['operation'] = $operation;
                        $response['data'] = $data;
                        $response['message'] = "{$operation}ed";

                        // append data return from $this->_hook() method in the controller
                        // to $response variable if not null and empty
                        if (!is_null($this->__hook_data) || !empty($this->__hook_data))
                            $response['hook'] = $this->__hook_data;
                    // } else {
                    //     $response['success'] = false;
                    //     $response['message'] = "Unable to {$operation} due to system error.";
                    // }
                } else {
                    $response['success'] = false;
                    $response['message'] = !is_null($relationship_id)? "update-failed": "reached-maximum-attach-limit";
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

    public function search(Request $request, $relationName=null)
    {
        $response = ['result' => []];

        $class = $this->_model;
        $model = new $class();

        $term = $request->get('term', null);
        $relationship = $request->get('relationship', []);
        $limit = $request->get('limit', 10);

        $search_columns = $this->getSearchColumns($relationName);

        if (count($search_columns) > 1) {
            $model->where(function($query) use ($search_columns, $term) {
                foreach ($search_columns as $column) {
                    $query->where($column, 'like', "%$term%");
                }
            });
        } else {
            $model->where($search_columns[0], 'like', "%$term%");
        }

        if(!empty($relationship)) {
            $model->with($relationship);
        }

        if($limit)
            $model->limit($limit);

        $response['result'] = $model->get();

        return $response;
    }

    public function get (Request $request, $id, $relationModel=null, $relationModelId=null)
    {
        $loadModelQueryString = $request->get('load_models');
        $where = $request->get('filters');
        $valideRelationModels = $this->_getRelationModels($relationModel);
        $modelObj = null;
        
        if (!is_null($relationModel)) {
            $modelObj = $this->_model::findOrFail(intval($id));
        } else {
            $modelObj = $this->_model::find(intval($id));
            if (is_null($modelObj)) return null;
        }

        if (!is_null($relationModel)) {
            if(! in_array($relationModel, $this->_getRelationModels()) ) {
                return abort(404, "Not found");
            }

            if (!is_null($relationModelId)) {
                $modelObj = $modelObj->$relationModel->find($relationModelId);
                if (is_null($modelObj)) return abort(404, "Not found");
            } else {
                $modelObj = $modelObj->$relationModel()->first();
            }

        }

        $this->__callAuthorize('get', $modelObj, $relationModel);

        if (!is_null($loadModelQueryString) && !empty($valideRelationModels)) {
            $loadModelQueryStringArr = explode($this->_getQueryStringLoadModelDelimiter(), $loadModelQueryString);
            
            // remove all the elements in $loadModelQueryStringArr that do not exist 
            // in the $validateRelationModels
            $loadModel = array_intersect($loadModelQueryStringArr, $valideRelationModels);
        }

        if (!empty($loadModel)) {
            $modelObj = $modelObj->load($loadModel);
        }

        //call the $this->_resource() method if defined in the controller.
        //and return the data if not null

        return $this->__makeResource('get', $modelObj, $relationModel, $relationModelId);
    }

    public function fetch (Request $request, $id = null, $relationModel=null)
    {
        
        $this->__callAuthorize("fetch");

        $returnCount = $request->get('count', false);
        $sort = $request->get('sort');
        $where = $request->get('filters');
        $fields = $request->get('fields', []);
        $relationshipQueryString = $request->get('with');
        $countRelationship = $request->get("count_with", false);
        $getFirst = $request->get("get_first", false);
        $paginate = $request->get('paginate');
        $limit = $request->get('limit');
        $offset = $request->get('offset');

        $valideRelationModels = $this->_getRelationModels();
        $sortColumns = $this->_getSortColumns($relationModel);

        $model = $this->_model;
        $instance = new $model();

        if (!is_null($id) && is_numeric($id) && !is_null($relationModel)) {
            if (!in_array($relationModel, $valideRelationModels)) {
                return abort(404, message: "$relationModel relation model not defined.");
            }
            $valideRelationModels = $this->_getRelationModels($relationModel);

            $instance = $instance->findOrFail(intval($id));

            $instance = $instance->$relationModel();
        }

        if ($where && is_string($where)) {
            $where_decode = json_decode($where, true);
            $where_clauses = $this->__getWhereClause($where_decode);
            if (!empty($where_clauses))
                $instance = $instance->where($where_clauses);
        }

        if (!is_null($relationshipQueryString) && !empty($valideRelationModels)) {
            $loadModelQueryStringArr = explode($this->_getQueryStringLoadModelDelimiter(), $relationshipQueryString);
            
            // remove all the elements in $loadModelQueryStringArr that do not exist 
            // in the $validateRelationModels
            $withModels = array_intersect($loadModelQueryStringArr, $valideRelationModels);
            $withMethod = ($countRelationship) ? "withCount" : "with";
            $instance = $instance->$withMethod($withModels);
        }

        if ($getFirst == true) {
            $result = $instance->first();
            $this->__callAuthorize('get', $result, $relationModel);
            return $this->__makeResource('fetch', $result, $relationModel);
        }

        // Sorting the query result
        if ($sort && !empty($sortColumns)) {
            if (is_array($sort) && (count($sort) == 2)) {
                if (in_array($sort[0], $sortColumns))
                    $instance = $instance->orderBy($sort[0], $sort[1]);
            } elseif (is_string($sort)) {
                if (in_array($sort, $sortColumns))
                    $instance = $instance->latest($sort);
            }
        }
        
        // Limiting the query result
        if (is_numeric($limit)) {
            $instance = $instance->limit((int) $limit);
        }

        if (is_numeric($offset)) {
            $instance = $instance->offset($limit);
        }

        if (!empty($fields)) {
            return $instance->get($fields);
        }

        if ( !is_null( $this->_getPaginate() ) ) {
            $results = $instance->paginate( $this->_getPaginate() );
        } else if( !is_null($paginate) && is_numeric($paginate) ) {
            $results = $instance->paginate((int) $paginate);
        } else {
            if ($returnCount) {
                $results = $instance->count();
            } else {
                $results = $instance->get();
            }
        }

        return $this->__makeResource('fetch', $results, $relationModel);
    }
    
    public function destroy (Request $request, $id)
    {
        // validate id is number or commer-separated numbers
        if (!is_numeric($id)) {
            if (!Str::isCommerSeparatedNumbers($id)) {
                return response(
                    content: "Invalid ID: ID must be a number or commer-separated numbers.",
                    status: 422
                );
            }
        }

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
        $auth_return = $this->__callAuthorize($operation, $id);
        if (!is_null($auth_return)) {
            return $auth_return;
        }

        if ( $this->_model::destroy($id)) {
            $this->__callHook($request, $id, 'destroyed');
            return 'true';
        }
        return 'false';
    }

    private function _serializeDeattachReponse($operation, $value, $res=null)
    {
        switch($operation) {
            case "sync":
            case "syncWithoutDetaching":
                return $res;

            default:
                return $value;
        }
    }

    private function __getWhereClause($where, $relationModel=null) 
    {
        $filterVar = "_result_filters";
        if (!is_null($relationModel)) {
            $filterVar = "_{$relationModel}{$filterVar}";
        }

        if (isset($this->$filterVar)) { // Checks if _result_filters or _{relation}_result_filters is set in the parent controller
            if (is_array($where) && Arr::isAssoc($where) && !HelpersArr::hasEmptyMulti($where)) { // Checks if $where is an array and associative array
                $filters = $this->$filterVar;
                $query_filter = array_intersect_key($where, $filters);
                $where_clauses = Arr::map($query_filter, function($value, string $key) use($filters) {
                    $operator = $filters[$key];
                    $field = $key;
                    if (strpos($key, "__") !== false) {
                        $field = explode("__", $key)[0];
                    }
                    return [$field, $operator, $value];
                });
                return array_values($where_clauses);
            }
        }
        return [];
    }

    /**
     * Gets user pre-defined relationship
     */
    protected function _getRelationModels($relationship=null)
    {
        $relation_model_property = "_relation_models";
        if (!is_null($relationship)) {
            $relation_model_property = "_{$relationship}_relation_models";
        }
        
        if (isset($this->$relation_model_property)) {
            return $this->$relation_model_property;
        }
        return [];
    }

    /**
     * Gets user pre-defined Columns to use for ordering
     */
    protected function _getSortColumns($relationship=null)
    {
        $relation_model_property = "_sort_columns";
        if (!is_null($relationship)) {
            $relation_model_property = "_{$relationship}_sort_columns";
        }
        
        if (isset($this->$relation_model_property)) {
            return $this->$relation_model_property;
        }
        return [];
    }

    /**
     * Return the user predified delimiter used to separate a model relationship
     * in the request query string, that will eager-load during model retriever 
     * and if not specified a default ":" will be used
     * 
     * @return string 
     */
    protected function _getQueryStringLoadModelDelimiter(): string
    {
        if (isset($this->_load_model_delimiter)) {
            return $this->_load_model_delimiter;
        }
        return ":";
    }

    protected function _getPaginate(): int|null
    {
        if (isset($this->_paginate)) {
            return $this->_paginate;
        }
        return null;
    }

    public function setValidateRules($validate_rules = [], $validate_message = [])
    {
        $this->__validate_message = $validate_message;
        $this->__validate_rules = $validate_rules;
    }

    private function __validate (Request $request, $relationModelName=null, $parentModel=null)
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
                    $validate_message = $this->$message_method($parentModel);
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
        return [];
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
            $this->__hook_data = $this->$method($request, $model, $flag);
            // return call_user_func_array([$this, $method], [$request, $model, $flag]);
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
    private function __callAuthorize ($operation, $model=null, $relationship=null) 
    {
        $method = (!is_null($relationship))? "_{$relationship}_authorize" : '_authorize';
        if (method_exists($this, $method)) {
            return $this->$method($operation, $model);
        }
    }

    private function __makeResource($method, $results, $relationModel=null, $relationModelId=null)
    {
        $resource_method = "_resource";
        if (!is_null($relationModel)) {
            $resource_method = "_{$relationModel}_resource";
        }

        if (method_exists($this, $resource_method)) {
            $resource = $this->$resource_method($method, $results, $relationModel, $relationModelId);
            if (!is_null($resource)) return $resource;
        }

        return $results;
    }

    public function setResponse ($response) 
    {
        $this->__response = $response;
    }

    public function getResponse()
    {
        return $this->__response;
    }

    public function getSearchColumns($relationName=null)
    {
        $property = !is_null($relationName)? 
            "_{$relationName}_search_columns" : '_search_columns';

        if (isset($this->$property)) {
            return $this->$property;
        }
    }

    public function setSearchColumns($columns=[], $relationName=null)
    {
        $property = !is_null($relationName)? 
            "_{$relationName}_search_columns" : '_search_columns';

        $this->$property = $columns;
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

    public function ignoreFormValidation()
    {
        return $this->_run_form_validation = false;
    }

    public function returnDataInEnterHook($bool=true) 
    {
        $this->_return_data_in_enter = $bool;
    }
}