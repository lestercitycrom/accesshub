<?php

// === PSR-12, tabs, краткие комментарии ===

// Путь к логам Laravel (корректный уровень выше public)
$logDir = realpath(__DIR__ . '/../storage/logs');
if (!$logDir) {
	die('Log directory not found');
}

// Bootstrap CDN
$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';

// Сколько строк трейс показывать
$traceLimit = 8; // Меняйте по необходимости

// Считываем самый свежий лог
$logFiles = glob($logDir . '/laravel*.log');
usort($logFiles, function ($a, $b) {
	return filemtime($b) - filemtime($a);
});
$logFile = $logFiles[0] ?? null;

if (!$logFile || !is_readable($logFile)) {
	die('Log file not found or not readable');
}

$isDebug = (isset($_GET['debug']) && $_GET['debug'] == '1');
$level = $isDebug ? 'debug' : 'error';

$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Функция: парсинг одной строки лога
function parseLogLine($line)
{
	if (preg_match('/^\[([^\]]+)]\s+([a-zA-Z0-9_.]+)\.([A-Z]+):\s+(.*)$/', $line, $m)) {
		return [
			'timestamp' => $m[1],
			'env'       => $m[2],
			'level'     => strtolower($m[3]),
			'message'   => $m[4],
		];
	}
	return null;
}

$entries = [];
$current = null;

foreach ($lines as $line) {
	$parsed = parseLogLine($line);
	if ($parsed) {
		if ($current) {
			$entries[] = $current;
		}
		$current = $parsed;
		$current['trace'] = [];
	} elseif ($current && (str_starts_with($line, '#') || preg_match('/^ {4}/', $line))) {
		$current['trace'][] = $line;
	} elseif ($current && trim($line) !== '') {
		$current['trace'][] = $line;
	}
}
if ($current) {
	$entries[] = $current;
}

$filtered = array_filter($entries, fn($e) => $e['level'] === $level);
$filtered = array_slice(array_reverse($filtered), 0, 15);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="UTF-8">
	<title>Laravel Log Viewer</title>
	<link href="<?= $bootstrapCss ?>" rel="stylesheet">
	<style>
		.copied-alert {
			position: fixed;
			top: 10px;
			right: 20px;
			z-index: 9999;
			display: none;
		}
	</style>
</head>
<body>
<div class="container my-4">
	<h1 class="mb-4">
		<?= $isDebug ? 'Debug Logs' : 'Error Logs' ?> (<?= htmlspecialchars(basename($logFile)) ?>)
	</h1>
	<div class="mb-3 d-flex align-items-start">
		<div>
			<a href="?debug=1" class="btn btn-outline-primary">Debug only</a>
			<a href="?" class="btn btn-outline-danger active">Errors only</a>
		</div>
		<div class="ms-auto text-end">
			<button class="btn btn-success mb-3" onclick="copyLatestLog()">Скопировать последнюю ошибку</button>
			<div class="alert alert-success copied-alert" id="copiedAlert" style="display: none;">Скопировано!</div>
		</div>	
	</div>

	<?php if (empty($filtered)): ?>
		<div class="alert alert-info">No log entries found.</div>
	<?php else: ?>
		<!-- Кнопка копирования самой свежей ошибки -->
		<div class="accordion" id="logAccordion">
			<?php foreach ($filtered as $idx => $entry): ?>
				<div class="accordion-item">
					<h2 class="accordion-header" id="heading<?= $idx ?>">
						<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $idx ?>" aria-expanded="false" aria-controls="collapse<?= $idx ?>">
							<span class="text-muted small">[<?= htmlspecialchars($entry['timestamp']) ?>]</span>&nbsp; <?= strtoupper($entry['level']) ?>: <?= htmlspecialchars(mb_strimwidth($entry['message'], 0, 100, '…')) ?>
						</button>
					</h2>
					<div id="collapse<?= $idx ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $idx ?>" data-bs-parent="#logAccordion">
						<div class="accordion-body">
							<strong>Time:</strong> <?= htmlspecialchars($entry['timestamp']) ?><br>
							<strong>Env:</strong> <?= htmlspecialchars($entry['env']) ?><br>
							<strong>Level:</strong> <?= strtoupper($entry['level']) ?><br>
							<strong>Message:</strong>
							<pre class="mb-2 log-message" id="log-message-<?= $idx ?>"><?= htmlspecialchars($entry['message']) ?></pre>
							<?php if (!empty($entry['trace'])): ?>
								<strong>Trace (max <?= $traceLimit ?>):</strong>
								<pre class="log-trace" id="log-trace-<?= $idx ?>" style="font-size:12px"><?= htmlspecialchars(implode("\n", array_slice($entry['trace'], 0, $traceLimit))) ?></pre>
								<?php if (count($entry['trace']) > $traceLimit): ?>
									<span class="text-muted">… (<?= count($entry['trace']) - $traceLimit ?> lines hidden)</span>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<hr>
	<small class="text-muted">
		Лог: <?= htmlspecialchars($logFile) ?> | Trace limit: <?= $traceLimit ?> строк | Отображается <?= count($filtered) ?> записей
	</small>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyLatestLog() {
	const firstMessage = document.querySelector('.log-message');
	const firstTrace = document.querySelector('.log-trace');
	let text = '';

	if (firstMessage) {
		text += firstMessage.textContent + "\n";
	}
	if (firstTrace) {
		text += firstTrace.textContent;
	}
	if (text.trim() !== '') {
		navigator.clipboard.writeText(text).then(function() {
			const alertBox = document.getElementById('copiedAlert');
			alertBox.style.display = 'block';
			setTimeout(function() {
				alertBox.style.display = 'none';
			}, 1500);
		});
	}
}
</script>
</body>
</html>
