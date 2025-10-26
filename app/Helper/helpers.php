<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

if (!function_exists('collectionToObject')) {
     /**
     * Nỗi đau mang tên collection, con quỷ khi đang gắng debug Mảng/object
     * Convert Eloquent Collection hoặc Model sang stdClass
     *
     * @param  \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model  $input
     * @return object
     *
     * @throws \InvalidArgumentException
     */
    function collectionToObject(Collection|Model $input)
    {
        // Nếu là Collection hoặc Model thì convert qua JSON rồi decode
        if ($input instanceof Collection || $input instanceof Model) {
            return (object) json_decode(json_encode($input));
        }

        // Đầu vào không hợp lệ
        throw new InvalidArgumentException(sprintf(
            'collectionToObject() chỉ chấp nhận Eloquent Collection hoặc Model, %s được truyền vào.',
            is_object($input) ? get_class($input) : gettype($input)
        ));
        return null;
    }
}


