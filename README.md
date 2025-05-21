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

        protected $_relation_models = ["profile", "posts"];
        protected $_fillable = ["username", "email"];

        protected $_profile_fillable = ["firstname", "lastname", "date_of_birth"];

        protected $_result_filters = [
            "active" => "=",
            "status" => "="
            ...
        ];

        protected function _validate_rules()
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

        // Define the validation rule for the relationship profile
        protected function _validate_profile_rule(): array
        {
            return [
                "firstname" => ["string", "max:20"],
                ...
            ];
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



