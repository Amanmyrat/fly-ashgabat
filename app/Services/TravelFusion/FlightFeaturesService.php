<?php

namespace App\Services\TravelFusion;

class FlightFeaturesService
{
    public function getRelevantFeatures(array $features, string $supplierClass, string $operatorCode): array
    {
        $relevantFeatures = [
            'HoldBag' => false,
            'SmallCabinBag' => false,
            'LargeCabinBag' => false,
            'FlightChange' => false,
            'Cancellation' => false,
        ];

        foreach ($features['Feature'] as $feature) {
            $featureType = $feature['@attributes']['Type'] ?? '';
            $options = $feature['Option'] ?? [];
            $options = isset($options[0]) ? $options : [$options];

            // Skip if the feature type is not in the required list
            if (!in_array($featureType, ['HoldBag', 'SmallCabinBag', 'LargeCabinBag', 'FlightChange', 'Cancellation'])) {
                continue;
            }

            foreach ($options as $option) {
                $conditions = $option['Condition'] ?? [];
                $price = $option['@attributes']['Value'] ?? '';
                $currency = $option['@attributes']['Currency'] ?? '';

                if (!isset($option['@attributes']['Value'])) {
                    continue;
                }
                $isMatch = true;

                foreach ($conditions as $condition) {
                    $conditionType = $condition['@attributes']['Type'] ?? '';
                    $conditionValue = $condition['@attributes']['Value'] ?? '';

                    // Match SupplierClass and Direction
                    if ($conditionType === 'SupplierClass') {
                        $conditionValueFirstPart = explode(',', $conditionValue)[0];
                        if ($conditionValueFirstPart !== $supplierClass) {
                            $isMatch = false;
                            break;
                        }
                    }
                    if ($conditionType === 'OperatorCode') {
                        $conditionValueFirstPart = explode(',', $conditionValue)[0];
                        if ($conditionValueFirstPart !== $operatorCode) {
                            $isMatch = false;
                            break;
                        }
                    }

//                    if ($conditionType === 'Direction' && $conditionValue !== $direction) {
//                        $isMatch = false;
//                        break;
//                    }
                }

                if ($isMatch) {
                    // Format the feature based on conditions
                    $maxQuantity = null;
                    $description = null;
                    $isBundled = false;

                    foreach ($conditions as $condition) {
                        if ($condition['@attributes']['Type'] === 'MaxQuantity') {
                            $maxQuantity = (int)$condition['@attributes']['Value'];
                        }

                        if ($condition['@attributes']['Type'] === 'Dimensions') {
                            $description = $condition['@attributes']['Value'];
                        }
                        if ($condition['@attributes']['Type'] === 'MaxWeight') {
                            $description = $condition['@attributes']['Value'];
                        }
                        if ($condition['@attributes']['Type'] === 'Provision') {
                            $isBundled = $condition['@attributes']['Value'] == 'Bundled';
                        }
                    }

                    if (in_array($featureType, ['HoldBag', 'SmallCabinBag', 'LargeCabinBag'])) {
                        if ($isBundled){
                            $formattedValue = $maxQuantity && $description ? "{$maxQuantity} x ($description)" : null;
                        }else{
                            $formattedValue = $price . ' ' . $currency;
                        }
                    } else {
                        $formattedValue = $price . ' ' . $currency;
                    }

                    $relevantFeatures[$featureType] = $formattedValue ? [
                        'Bundled' => $isBundled,
                        'Value' => $formattedValue,
                    ] : false;
                }
            }
        }

        if (!empty($relevantFeatures['SmallCabinBag']) && is_array($relevantFeatures['SmallCabinBag'])) {
            $cabinBag = $relevantFeatures['SmallCabinBag'];
        } elseif (!empty($relevantFeatures['LargeCabinBag']) && is_array($relevantFeatures['LargeCabinBag'])) {
            $cabinBag = $relevantFeatures['LargeCabinBag'];
        } else {
            $cabinBag = false;
        }

        $relevantFeatures['CabinBag'] = $cabinBag;

        unset($relevantFeatures['SmallCabinBag'], $relevantFeatures['LargeCabinBag']);

//        if ($supplierClass == 'Light') {
//            dd($relevantFeatures, $features, $supplierClass, $operatorCode);
//        }
        return $relevantFeatures;

    }


}
