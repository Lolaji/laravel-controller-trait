<?php
namespace Lolaji\LaravelControllerTrait;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Lolaji\LaravelControllerTrait\Helpers\Arr as HelpersArr;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Lolaji\LaravelControllerTrait\Enum\DeattachMethodEnum;
use Lolaji\LaravelControllerTrait\Exceptions\RequestMethodException;
use Lolaji\LaravelControllerTrait\Filters\MorphFilter;
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

        if ($this->_run_form_validation) {
            if ($this->__validate($request)){
                return $this->getResponse();
            }
        }

        $preExe = $this->__callPreExecute($operation, $instance);
        if (!is_null($preExe)) {
            return $preExe;
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
        // Check if the relation model is decleared in the _relation_models array
        // and return 404 if not in the array
        $this->_abortIfRelationNotExist($relationModel);

        $parentModel = $this->_model::findOrFail(intval($id));

        $response = ['success'=>false, 'message'=>[]];

        // Run $this->_before_validate() method if exist
        if (method_exists($this, '_enter')) {
            $enter = $this->_enter('upsertByForeign', $request, $id, $relationModel, $relationModelId);
            if ($this->_return_data_in_enter) {
                return $enter;
            }
        }

        // Run $this->_authorize() method if defined in parent controller
        $this->__callAuthorize("get", $parentModel);

        $modelObj = $parentModel->$relationModel();
        $operation = 'create';
        $childModel = null;

        if (!is_null($relationModelId)) {
            $operation = 'update';
            $childModel = $parentModel->$relationModel()->findOrFail(intval($relationModelId));
        } else {
            if (isset($parentModel->$relationModel->id)) {
                $operation = 'update';
            }
        }

        // Run $this->_{relationship_method}_authorize() method if defined in controller
        // and return result if not null
        $this->__callAuthorize($operation, $childModel, $relationModel);

        if ($this->_run_form_validation) {
            if ($this->__validate(
                request: $request, 
                relationModelName: $relationModel, 
                parentModel: $parentModel
            )) {
                return $this->getResponse();
            }
        }

        $preExe = $this->__callPreExecute(
            operation: $operation, 
            model: $childModel, 
            relationModelName: $relationModel,
            parentModel: $parentModel,
        );

        if (!is_null($preExe)) {
            return $preExe;
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
        // Abort if relation model is not null and is not defined 
        // in the parent controller
        $this->_abortIfRelationNotExist($relationship);

        $response = ['success' => false, 'message'=>''];
        // $operations = ['detach', 'attach', 'sync', 'syncWithoutDetaching', 'updateExistingPivot', 'syncWithPivotValues'];

        // form input
        $operation = $request->post('operation');
        $value = $request->post('value');

        $this->_abortIfManyToManyOperationNotExist($operation, $relationship);

        // if (in_array($operation, $operations)) {
        $model = $this->_model;
        $instance = (new $model())->findOrFail($id);

        $this->__callAuthorize($operation, $instance);

        $is_reach_max_attach_limit = $this->__reachedMaxAttachLimit($instance, $relationship);

        if ($value && (!empty($value) || !is_null($value))) {
            if (!$is_reach_max_attach_limit || !is_null($relationship_id)) {
                $data = null;
                $res = null;

                // if (in_array($relationship, $this->_getRelationModels())) {
                if (!is_null($relationship_id)) {
                    $res = $instance->$relationship()->$operation($relationship_id, $value);
                } else {
                    $res = $instance->$relationship()->$operation($value);
                }
                // } else {
                //     return RequestMethodException::response(
                //         debug_message: "Deattach Method Error: \"{$relationship}\" model does not exist and/or is not decleared in the parent controller.",
                //         debug_status: 409,
                //         message: "Resource could not be found",
                //         status: 404
                //     );
                // }

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
        // } else {
        //     $response['success'] = false;
        //     $response['message'] = 'invalid-operation-method';
        // }
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
        // Abort if relation model is not null and is not defined 
        // in the parent controller
        $this->_abortIfRelationNotExist($relationModel);

        $loadModelQueryString = $request->get('with');
        $countRelationship = $request->get('count_with', false);
        $modelObj = null;
        
        if (!is_null($relationModel)) {
            $modelObj = $this->_model::findOrFail(intval($id));
        } else {
            $modelObj = $this->_model::find(intval($id));
            if (is_null($modelObj)) return null;
        }

        if (!is_null($relationModel)) {
            if (!is_null($relationModelId)) {
                $modelObj = $modelObj->$relationModel->find($relationModelId);
                if (is_null($modelObj)) return abort(404, "Not found");
            } else {
                $modelObj = $modelObj->$relationModel()->first();
            }

        }

        $this->__callAuthorize('get', $modelObj, $relationModel);

        $loadModels = $this->_getExistingRelationsFromQueryString($loadModelQueryString, $relationModel);

        if (!empty($loadModels)) {
            $loadMethod = ($countRelationship == true) ? "loadCount" : "load";
            $modelObj = $modelObj->$loadMethod($loadModels);
        }

        //call the $this->_resource() method if defined in the controller.
        //and return the data if not null
        return $this->__makeResource('get', $modelObj, $relationModel, $relationModelId);
    }

    public function fetch (Request $request, $id = null, $relationModel=null)
    {
        // Abort If relation model is not null and not defined in the parent controller
        $this->_abortIfRelationNotExist($relationModel);

        if (is_null($id)) {
            $this->__callAuthorize("fetch");    
        }

        $returnCount = $request->get('count', false);
        $sort = $request->get('sort');
        $where = $request->get('filters');
        $fields = $request->get('fields', []);
        $relationshipQueryString = $request->get('with');
        $countRelationship = $request->get("count_with", false);
        $getFirst = $request->get("get_first", false);
        $paginate = $request->get('paginate');
        $page = $request->get("page", 1);
        $limit = $request->get('limit');
        $offset = $request->get('offset');

        $valideRelationModels = $this->_getRelationModels();
        $sortColumns = $this->_getSortColumns($relationModel);

        $model = $this->_model;
        $instance = new $model();

        if (!is_null($id) && !is_null($relationModel)) {
            $instance = $instance->findOrFail(intval($id));

            // Call _authorize() method in the parent model controller
            $this->__callAuthorize("get", $instance);

            
            if ($instance->$relationModel() instanceof HasOne) {
                return $this->__makeResource(
                    method: "get",
                    results: $instance->$relationModel,
                );
            }

            $instance = $instance->$relationModel();
        }

        // Where Clause
        if ($where && is_string($where)) {
            $where_decode = json_decode($where, true);
            $clause = $this->__getWhereClause($where_decode, $relationModel);
            if (isset($clause["where"]) && !empty($clause["where"])) {
                $instance = $instance->where($clause["where"]);
            } 

            if (isset($clause["wherePivot"]) && !empty($clause["wherePivot"])) {
                foreach($clause["wherePivot"] as $key => $value) {
                    $instance = $instance->wherePivot($value[0], $value[2]);
                }
            }

            if (isset($clause["whereMorph"]) && !empty($clause["whereMorph"])) {
                $columns = [];
                foreach ($clause["whereMorph"] as $key => $val) {
                    $instance = $instance->whereHasMorph($val->morphName, $val->type, function(Builder $query, string $type) use ($val, &$columns) {
                        $column = $val->getColumn($type);
                        array_push($columns, $column);
                        if (isset($val->relation) && !is_null($val->relation)) {
                            $query->whereRelation($val->relation, $column, $val->operator, $val->getValue());
                        } else {
                            $query->where($column, $val->operator, $val->getValue());
                        }
                    });
                }
                return $columns;
            }

            // Call the _filter or _{relationship}_filter method
            // if defined in the controller.
            $instance = $this->__callFilterHook("fetch", $instance, $relationModel);
        }

        $withModels = $this->_getExistingRelationsFromQueryString($relationshipQueryString, $relationModel);
        if(!empty($withModels)) {
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
            $sortArr = explode(":", $sort);
            $sortLen = count($sortArr);
            
            if (is_array($sortArr) && $sortLen > 0 && in_array($sortArr[0], $sortColumns)) {
                if ($sortLen == 2 && in_array($sortArr[1], ["asc", "desc"])) {
                    $instance = $instance->orderBy($sortArr[0], $sortArr[1]);
                } else {
                    $instance = $instance->latest($sortArr[0]);
                }
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
            $results = $instance->paginate( $this->_getPaginate(), ["*"], "page", (int) $page );
        } else if( !is_null($paginate) && is_numeric($paginate) ) {
            $results = $instance->paginate((int) $paginate, ["*"], "page", (int) $page);
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

        if (isset($this->$filterVar) || method_exists($this, $filterVar)) { // Checks if _result_filters or _{relation}_result_filters is set in the parent controller
            if (is_array($where) && Arr::isAssoc($where) && !HelpersArr::hasEmptyMulti($where)) { // Checks if $where is an array and associative array
                $filters = $this?->$filterVar ?? $this->$filterVar(); // Get the property first and if not defined, gets the method.
                $query_filter = array_intersect_key($where, $filters);
                $clauses = ["where" => [], "wherePivot" => [], "whereMorph" => []];
                Arr::map($query_filter, function($value, string $key) use($filters, &$clauses) {
                    $operator = $filters[$key];
                    $field = $key;
                    $isPivot = false;

                    if ($operator instanceof MorphFilter) {
                        $operator->setValue($value);
                        array_push($clauses["whereMorph"], $operator);
                    } else {
                        if (strpos($key, "__") !== false) {
                            $arr = explode("__", $key);
                            $field = $arr[0];
                            if (in_array("pivot", $arr)) {
                                $isPivot = true;
                            }
                        }

                        $val = $operator == "like"? "%$value%" : $value;

                        if ($isPivot) {
                            array_push($clauses["wherePivot"], [$field, $operator, $val]);
                        } else {
                            array_push($clauses["where"], [$field, $operator, $val]);
                        }
                    }

                });
                return $clauses;
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

    protected function _abortIfRelationNotExist($relationModelName=null)
    {
        if (!is_null($relationModelName)) {
            $validRelationModels = $this->_getRelationModels();
    
            if (!in_array($relationModelName, $validRelationModels)) {
                $route = request()->path();
                return abort(404, "The route $route is not found.");
            } 
        }
    }

    /*--------------------------------------------------------
     |  Gets pre-defined many-to-many operations
     |--------------------------------------------------------
     |
     |  Gets the allowed many-to-many methods decleared on
     |  in the controller for the parent or relationship model
     |
     */

     protected function _getManyToManyOperation($relationship=null)
     {
        $operation_property = "_deattach_methods";

        if (isset($this->$operation_property[$relationship])) {
            return $this->$operation_property[$relationship];
        } else if (method_exists($this, $operation_property)) {
            $method = $this->$operation_property();
            return isset($method[$relationship]) ? $method[$relationship] : [];
        }
        return [];
     }

     protected function _abortIfManyToManyOperationNotExist($operation, $relationModelName=null)
     {
        $allowedOperations = $this->_getManyToManyOperation($relationModelName);

        if (!in_array($operation, $allowedOperations)) {
            abort(400, "Invalid operation Method: $operation could not be found.");
        }
     }

    /*
     |------------------------------------------------
     | Get Exist Relation From Query String
     |------------------------------------------------
     |
     | Remove all the relation models that are included in the
     | query string, that are not decleared in the controller
     | and return the ones that are decleared in the controller
     | if they are included in the query string.
     */
    protected function _getExistingRelationsFromQueryString($queryString=null, $relationModel=null)
    {
        $declearedEelationModels = $this->_getRelationModels($relationModel);

        if (!is_null($queryString) && !empty($declearedEelationModels)) {
            $loadModelQueryStringArr = explode($this->_getQueryStringLoadModelDelimiter(), $queryString);
            
            // remove all the elements in $loadModelQueryStringArr that do not exist 
            // in the $validateRelationModels
            $models = array_intersect($loadModelQueryStringArr, $declearedEelationModels);
            return array_values($models);
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
                
                return $validatation($this->$method($parentModel), $validate_message);
            }
        }
    }

    private function __getRequestData(Request $request, $is_relational=false, $relationModelName="")
    {
        $property = ($is_relational)? "_{$relationModelName}_fillable" : "_fillable";
        $serializerMethod = $is_relational? "_serialize_{$relationModelName}_input" : "_serialize_input";
        
        if (isset($this->$property) && !empty($this->$property)) {
            $cred = $request->only($this->$property);
            if (method_exists($this, $serializerMethod)) {
                $serializeInput = $this->$serializerMethod($cred);
                if (!is_null($serializeInput) && !empty($serializeInput)) {
                    return $serializeInput;
                }
                return $cred;
            }
            return $cred;
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
     * Call protected $this->_pre_execute() method
     * defined in controller inheriting this trait
     * before performing database operation
     * 
     * @param $operation database operation to be executed
     * @param $model model class
     * @param $relationModelName determine the relationship method name of the model perform database operation
     * 
     * @return void
     */
    private function __callPreExecute(string $operation, ?Model $model, ?string $relationModelName=null, ?Model $parentModel=null)
    {
        $childClassMethod = !is_null($relationModelName)? "_{$relationModelName}_pre_execute" : "_pre_execute";

        if ((int) method_exists($this, $childClassMethod)) {
            return $this->$childClassMethod($operation, $model, $parentModel);
        }
        return null;
    }

    private function __callFilterHook ($method, $instance, $relationModel=null)
    {
        $method = "_filter";
        if (!is_null ($relationModel)) {
            $method = "_{$relationModel}{$method}";
        }

        if (method_exists($this, $method)) {
            $inst = $this->$method("fetch", $instance);
            if (!is_null($inst)) {
                return $inst;
            } else {
                return $instance;
            }
        }
        return $instance;
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
        $property = "_relation_attach_limit";
        if (isset($this->$property) && isset($this->$property[$relationship])) {
            return count($instance->$relationship) >= $this->$property[$relationship];
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