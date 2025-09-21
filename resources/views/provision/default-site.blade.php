<?php
/**
 * BrokeForge Default Site
 * This is the default site template for newly provisioned servers.
 */

$server_name = gethostname() ?: 'Unknown';
$php_version = phpversion();
$server_time = date('Y-m-d H:i:s T');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BrokeForge - Server Ready</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem;
            max-width: 600px;
            text-align: center;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.2);
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .status {
            background: rgba(46, 213, 115, 0.2);
            border: 2px solid #2ed573;
            border-radius: 10px;
            padding: 1rem;
            margin: 2rem 0;
        }
        .info {
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        .info p {
            margin: 0.5rem 0;
            font-size: 1.1rem;
        }
        .footer {
            margin-top: 2rem;
            opacity: 0.8;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 BrokeForge</h1>
        <div class="status">
            <h2>✅ Server Successfully Provisioned</h2>
        </div>
        <div class="info">
            <p><strong>Server:</strong> <?= htmlspecialchars($server_name) ?></p>
            <p><strong>PHP Version:</strong> <?= htmlspecialchars($php_version) ?></p>
            <p><strong>Server Time:</strong> <?= htmlspecialchars($server_time) ?></p>
        </div>
        <p>Your server is ready to host applications. Deploy your first site through the BrokeForge control panel.</p>
        <div class="footer">
            <p>Powered by BrokeForge Server Management Platform</p>
        </div>
    </div>
</body>
</html>