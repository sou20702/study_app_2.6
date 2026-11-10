<?php
// 世界の状態をJSONで返す（ホームタブの描画担当が、本番の描画を作るときに使える）
require_once __DIR__ . '/../config/db_config.php';
json_out(['ok' => true] + get_world_state($pdo));
