<?php
return [
    'api_key'               => getenv('ROBOFLOW_API_KEY') ?: '',
    // Model 1: Undertone
    'undertone_model_id'    => getenv('ROBOFLOW_UNDERTONE_MODEL_ID') ?: (getenv('ROBOFLOW_MODEL_ID') ?: ''),
    'undertone_version'     => getenv('ROBOFLOW_UNDERTONE_VERSION') ?: (getenv('ROBOFLOW_VERSION') ?: '1'),
    // Model 2: Face Shape
    'face_shape_model_id'   => getenv('ROBOFLOW_FACE_SHAPE_MODEL_ID') ?: '',
    'face_shape_version'    => getenv('ROBOFLOW_FACE_SHAPE_VERSION') ?: '1',
];
