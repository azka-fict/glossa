<?php
return [
    'api_key'  => getenv('ROBOFLOW_API_KEY') ?: '',
    'model_id' => getenv('ROBOFLOW_MODEL_ID') ?: '',
    'version'  => getenv('ROBOFLOW_VERSION') ?: '1',
];
