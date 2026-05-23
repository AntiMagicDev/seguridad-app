<?php
require_once __DIR__ . '/src/bootstrap.php';

function db(): mysqli
{
    return Database::getConnection();
}
