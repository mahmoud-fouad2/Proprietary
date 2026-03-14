<?php
declare(strict_types=1);

namespace Zaco\Core;

use PDO;
use PDOException;

final class Db
{
    public static function fromConfig(Config $config): PDO
    {
        $dsn = (string)$config->get('db.dsn');
        $user = (string)$config->get('db.user');
        $pass = (string)$config->get('db.pass');

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Keep message generic in production
            throw $e;
        }

        return $pdo;
    }
}
