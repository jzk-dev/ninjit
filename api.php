<?php
  // No Render, pegue a "External Connection String" no painel do banco
  // Exemplo: postgres://user:password@host:port/dbname
  $connectionString = "host=seu-host.render.com dbname=seu-db user=seu-user password=sua-senha sslmode=require";
  $secret = 'SuaSenhaSuperSecreta123';

  $receivedToken = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
  $now = date('YmdHi');
  $prev = date('YmdHi', strtotime('-1 minute'));

  if ($receivedToken !== sha1($secret . $now) && $receivedToken !== sha1($secret . $prev)) {
    http_response_code(403);
    die(json_encode(["status" => "erro", "msg" => "Token invalido"]));
  }

  $jsonData = file_get_contents('php://input');
  $payload = json_decode($jsonData, true);

  if (!$payload || !isset($payload['StatsData'])) {
    http_response_code(400);
    die(json_encode(["status" => "erro", "msg" => "JSON vazio ou invalido"]));
  }

  $conn = pg_connect($connectionString);
  if (!$conn) {
    http_response_code(500);
    die(json_encode(["status" => "erro", "msg" => "Erro conexao Postgres"]));
  }

  pg_query($conn, "BEGIN");

  try {
    $iChaveUser = pg_escape_string($conn, $payload['iChaveUser']);

    foreach ($payload['StatsData'] as $row) {
      $dData = date('Y-m-d', strtotime(str_replace('/', '-', $row['dData'])));
      $iTempoTasks = (int)$row['iTempoTasks'];
      $iExecucoes = (int)$row['iExecucoes'];
      $iTempoExecs = (int)$row['iTempoExecs'];
      $iTeclas = (int)$row['iTeclas'];
      $iClicks = (int)$row['iClicks'];

      // Alinhamento SQL conforme o seu padrão
      $sql = "INSERT INTO Stats (iChaveUser, dData, iTempoTasks, iExecucoes, iTempoExecs, iTeclas, iClicks) 
                   VALUES ('$iChaveUser', '$dData', $iTempoTasks, $iExecucoes, $iTempoExecs, $iTeclas, $iClicks)
               ON CONFLICT (iChaveUser, dData) DO UPDATE SET 
                  iTempoTasks = EXCLUDED.iTempoTasks, 
                  iExecucoes = EXCLUDED.iExecucoes, 
                  iTempoExecs = EXCLUDED.iTempoExecs, 
                  iTeclas = EXCLUDED.iTeclas, 
                  iClicks = EXCLUDED.iClicks";
      
      $res = pg_query($conn, $sql);
      if (!$res) {
        throw new Exception(pg_last_error($conn));
      }
    }

    pg_query($conn, "COMMIT");
    echo json_encode(["status" => "sucesso"]);
  } catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    http_response_code(500);
    echo json_encode(["status" => "erro", "msg" => $e->getMessage()]);
  }

  pg_close($conn);
?>
