<?php

namespace App\Validation;

class DeliverymanValidation
{
    public static function acceptedTermRules()
    {
        return [
            "accepted_term" => "required"
        ];
    }

    public static function hardwareRules()
    {
        return [
            "hardware_id" => "required|exists:hardwares,id",
            "user_id" => "required|exists:users,id",
            "number" => "sometimes",
            "brand" => "sometimes",
            "model" => "sometimes",
            "price" => "sometimes",
            "is_with" => "sometimes",
            "service_id" => "sometimes",
            "service_provider" => "sometimes",
        ];
    }

    public static function updateHardwareRules()
    {
        return [
            "*.id" => "sometimes",
            "*.user_id" => "sometimes",
            "*.service_id" => "sometimes",
            "*.number" => "sometimes",
            "*.is_with" => "required_without:is_with",
            "id" => "sometimes",
            "user_id" => "sometimes",
            "service_id" => "sometimes",
            "number" => "sometimes",
            "is_with" => "sometimes"
        ];
    }

    public static function deliverymanStatusRule()
    {
        return [
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180',
            'online' => 'required|boolean'
        ];
    }

    public static function storeRules()
    {
        return [
            'latitude' => 'sometimes|required|numeric|min:-90|max:90',
            'longitude' => 'sometimes|required|numeric|min:-180|max:180'
        ];
    }

    public static function onlineDeliverymanRules()
    {
        return [
            'latitude' => 'required|numeric|min:-90|max:90',
            'longitude' => 'required|numeric|min:-180|max:180'
        ];
    }
}