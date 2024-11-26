# Laravel Controller Trait - A reuseable laravel controllers

Laravel Controller Trait is a Laravel reuseable trait that can help you reduce the amount of logic you write for your laravel application.

## Require

```sh
composer require lolaji/laravel-controller-trait

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

        protected $_relation_models = ["profile", "posts"];
        protected $_fillable = ["username", "email"];

        protected $_result_filters = [
            "active" => "=",
            "status" => "="
        ];

        protected function _validate_rules()
        {
            return [
                "name" => ['required', 'string'],
            ];
        }

        protected _hook($request, $model_result, $operation)
        {
            switch($opertion) {
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
    }



