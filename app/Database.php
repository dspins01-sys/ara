<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
final class Database {
  private static ?PDO $pdo = null;
  public static function pdo(): PDO {
    if (!self::$pdo) {
      self::$pdo = new PDO('sqlite:' . DB_PATH, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
      self::$pdo->exec('PRAGMA foreign_keys = ON');
    }
    return self::$pdo;
  }
}
