<?php
//require_once 'config.php';
require_once '../database.php';

// SQL para pegar apenas o registro mais recente de cada UUID
$sql = "SELECT t1.* FROM esp32_pings t1
        INNER JOIN (
            SELECT uuid, MAX(created_at) as max_date
            FROM esp32_pings
            GROUP BY uuid
        ) t2 ON t1.uuid = t2.uuid AND t1.created_at = t2.max_date
        ORDER BY t1.created_at DESC";

$pings = DBQ($sql);

// Funções de análise (Mantidas do seu layout anterior)
function analyzeLog($log) {
    $res = ['errors' => 0, 'ok' => 0, 'lines' => 0];
    if(empty($log)) return $res;
    $lines = explode("\n", $log);
    $res['lines'] = count(array_filter($lines));
    foreach($lines as $l){
        $low = strtolower($l);
        if(preg_match('/error|fail|falh|301/', $low)) $res['errors']++;
        if(preg_match('/ok|success|200/', $low)) $res['ok']++;
    }
    return $res;
}

function formatLog($log) {
    $lines = explode("\n", $log);
    $out = "";
    foreach($lines as $line){
        if(empty(trim($line))) continue;
        $class = "log-info";
        if(preg_match('/error|fail|falh|301/i', $line)) $class = "log-error text-danger";
        if(preg_match('/ok|success|200/i', $line)) $class = "log-success text-info";
        
        $line = preg_replace('/^\[(\d+)\]/', '<span class="text-primary">[$1]</span>', $line);
        $out .= "<div class='mb-1 small $class font-monospace'>".htmlspecialchars($line)."</div>";
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sondas Network Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0b0e14; color: #abb2bf; }
        .device-card { background: #1c2128; border: 1px solid #30363d; border-radius: 8px; margin-bottom: 20px; }
        .log-box { background: #000; height: 250px; overflow-y: auto; padding: 10px; border-radius: 4px; border: 1px solid #30363d; }
        .status-on { color: #238636; }
        .status-off { color: #da3633; }
    </style>
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-network-wired"></i> Monitor de Dispositivos</h2>
    
    <div class="row">
        <?php foreach($pings as $p): 
            $analysis = analyzeLog($p['log_content']);
            $is_online = (time() - strtotime($p['created_at'])) < 300;
        ?>
        <div class="col-12 mb-4">
            <div class="device-card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <div>
                        <h4 class="m-0 text-white">
                            <i class="fas fa-circle <?php echo $is_online ? 'status-on' : 'status-off'; ?> small"></i> 
                            <?php echo htmlspecialchars($p['site_esp']); ?>
                        </h4>
                        <small class="text-muted"><?php echo $p['uuid']; ?></small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-secondary"><?php echo $p['firmware_version']; ?></span><br>
                        <small><?php echo date('d/m/Y H:i:s', strtotime($p['created_at'])); ?></small>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-2 small"><strong>IP:</strong> <?php echo $p['remote_ip']; ?></div>
                    <div class="col-md-2 small"><strong>Board:</strong> <?php echo $p['board']; ?></div>
                    <div class="col-md-2 small"><strong>WiFi:</strong> <?php echo $p['ssid']; ?></div>
                    <div class="col-md-3 small"><strong>Sensor ID:</strong> <?php echo $p['sensor_id']; ?></div>
                    <div class="col-md-3 text-end">
                        <span class="badge bg-danger"><?php echo $analysis['errors']; ?> Erros</span>
                        <span class="badge bg-info text-dark"><?php echo $analysis['ok']; ?> Sucessos</span>
                    </div>
                </div>

                <div class="log-box">
                    <?php echo formatLog($p['log_content']); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script>setTimeout(() => location.reload(), 30000);</script>
</body>
</html>