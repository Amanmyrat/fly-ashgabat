<?php

namespace App\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;

class AddLocalizationHeaderExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        if (\count($routeInfo->phpDoc()->getTagsByName('@localizationHeader'))) {
            $operation->addParameters([
                Parameter::make('Accept-Language', 'header')
                    ->setSchema(
                        Schema::fromType(new StringType())
                    ),
            ]);
        }
    }
}
