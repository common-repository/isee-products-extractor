<?php

$constants = [
    'ADMIN_AJAX_SECURITY' => 'ijSXceapNAHDX9oBNecwgwBK',
];

foreach ($constants as $key => $value) {
    putenv("$key=$value");
}
