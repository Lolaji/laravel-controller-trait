<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitab755520d86520bc8fe750361cdc1fad
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'Lolaji\\LaravelControllerTrait\\' => 30,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Lolaji\\LaravelControllerTrait\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitab755520d86520bc8fe750361cdc1fad::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitab755520d86520bc8fe750361cdc1fad::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitab755520d86520bc8fe750361cdc1fad::$classMap;

        }, null, ClassLoader::class);
    }
}
