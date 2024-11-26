# Laravel Controller Trait - A reuseable laravel controllers

Laravel Controller Trait is a Laravel reuseable trait that can help you reduce the amount of logic you write for your laravel application.

## Usage

First create your controller, add ""use Lolaji\LaravelControllerTrait\LaravelControllerTrait"" trait

<code>
<?php

    namespace App\HTTP;

    class UserController extents Controller
    {
        use Lolaji\LaravelControllerTrait\LaravelControllerTrait;

        protected $_model = User::class;

        protected $_fillable = ["name", "email"];

        protected function _validate_rules()
        {
            return [
                "name" => ['required', 'string'],
            ];
        }

        protected _hook($request, $model_result, $operation)
        {
            switch($opertion) {
                case 'create':
                        // Code here execute after user is created
                    break;

                case 'update':
                        // Code here execute after user is updated
                    break;

                case 'destroy':
                        // Code here execute after user is destroy
                    break;
            }
        }
    }
</code>

