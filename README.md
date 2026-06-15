# Laravel Controller Trait - A reuseable laravel controllers

Laravel Controller Trait is a Laravel reuseable package that helps you reduce the amount of logic you write for your laravel application.

## Install Package

```sh
composer require lolaji/laravel-controller-trait

```
or add the package to your composer.json

```json
{
    "lolaji/laravel-controller-trait": "^2.0.0"
}

```

Then, run

```sh
composer update

```

## Usage

First create your controller, add ""use Lolaji\LaravelControllerTrait\LaravelControllerTrait"" trait

```php
<?php

    namespace App\HTTP;

    class UserController extents Controller
    {
        use Lolaji\LaravelControllerTrait\LaravelControllerTrait;

        protected $_model = User::class;

        // Return the total number of parent model (in this case User model)
        // if count=true is added to the query string
        // and if this is set to false or not declared 
        // it will not return the total number of the parent model regardless
        // wheather you add count=true query string
        protected bool $_enable_result_count=true;

        protected array $_relation_models = ["profile", "posts"];

        // Perform the same function as the $_enable_count_result except
        // it works specifically for the relation model you declared in $_relation_models
        protected bool $_enable_posts_result_count=true;

        // Return the HTTP response of the form error and requests if set to true
        // This means it calls the ->validate() method on 
        // Validator::make(..., ...) like  Validator::make(..., ...)->validate()
        // and response()->json(...) after request completed.
        protected bool $_enable_http_response = true;
        protected bool $_enable_posts_http_response = true;

        // When $_disable_operations or $_disable_{relationship}_operations is defined
        // those value assigned would return 405 Not Allowed
        protected array $_disable_operations = ["upsert", "fetch", ...];
        protected array $_disable_posts_operations = ["upsert", "fetch", ...];

        protected array $_fillable = ["username", "email"];

        protected array $_profile_fillable = ["firstname", "lastname", "date_of_birth"];

        // The database columns the results will be sorted
        // if not provided the sort (like ->orderby(...)) would not be applied
        protected array $_sort_columns = ["id", "created_at"];

        protected array $_result_filters = [
            "active" => "=",
            "status" => "="
            ...
        ];

        protected function _validate_rules(): array
        {
            return [
                "username" => ['required', 'string'],
                "email" => ['required', 'string'],
                ...
            ];
        }

        // if you would like to serialize the fillable data
        protected function _serialze_input(array $input): array
        {
            return $input;
        }

        protected _hook(Request $request, $model_result, $operation)
        {
            switch($operation) {
                case 'created':
                        // Code here execute after user is created
                    break;

                case 'updated':
                        // Code here execute after user is updated
                    break;

                case 'attach':
                        // Code here execute after attached
                    break;

                case 'detach':
                        // Code here execute after detached
                    break;

                case 'sync':
                        // Code here execute after synced
                    break;

                case 'updateWithoutDetaching':
                        // Code here execute after updateWithoutDetaching
                    break;

                case 'updateExistingPivot':
                        // Code here execute after updateExistingPivot
                    break;

                case 'syncWithPivotValues':
                        // Code here execute after syncWithPivotValues
                    break;

                case 'destroyed':
                        // Code here execute after user is destroy
                    break;
            }
        }

        // Define the validation rule for the relationship profile
        protected function _validate_profile_rule(): array
        {
            return [
                "firstname" => ["string", "max:20"],
                ...
            ];
        }

        // if you would like to serialize the User form fillable data
        protected function _serialze_input(array $input): array
        {
            return $input;
        }

        // if you would like to serialize the profile relationship fillable data
        protected function _serialze_profile_input(array $input): array
        {
            return $input;
        }

        // Define the relationship "profile" hook
        protected function _profile_hook(Request $request, $model, $operation)
        {
            // Perform code execution like in _hook method
        }
    }



