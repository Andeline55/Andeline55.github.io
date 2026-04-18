<?php
$conn = new mysqli("localhost", "root", "", "youtube");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$stop_words = [
    'the','and','is','in','to','of','for','on','with','a','an',
    'this','that','are','be','as','by','from','at','it','you',
    'your','we','our','they','their','i'
];

$keyword_map = [
    'tech' => 'technology',
    'technologi' => 'technology',
    'latest' => 'news'
];

$selectedKeyword = $_GET['keyword'] ?? 'all';

$dropdownOptions = [
    'all' => 'All',
    'technology' => 'Technology',
    'artificial intelligence' => 'AI',
    'smartphone' => 'Smartphone',
    'future tech' => 'Future Tech'
];

$sql = "SELECT title, description FROM videos";
$result = $conn->query($sql);

$word_count = [];

while ($row = $result->fetch_assoc()) {

    $text = strtolower($row['title'] . ' ' . $row['description']);

    if ($selectedKeyword !== 'all' && strpos($text, $selectedKeyword) === false) {
        continue;
    }

    $text = preg_replace('/[^a-z\s]/', ' ', $text);
    $words = explode(' ', $text);

    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) < 3) continue;
        if (in_array($word, $stop_words)) continue;
        if (isset($keyword_map[$word])) {
            $word = $keyword_map[$word];
        }
        $word_count[$word] = ($word_count[$word] ?? 0) + 1;
    }
}

arsort($word_count);
$top3 = array_slice($word_count, 0, 3, true);

if (empty($top3)) {
    $top3 = ['no data' => 0];
}

$labels = array_keys($top3);
$data = array_values($top3);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Trending Topics Analysis</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<h1>Top 3 Trending Topics</h1>

<form method="get">
    <label>Select Topic:</label>
    <select name="keyword">
        <?php foreach ($dropdownOptions as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>"
                <?= ($selectedKeyword === $value) ? 'selected' : '' ?>>
                <?= $label ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Analyze</button>
</form>

<hr>

<ul>
<?php foreach ($top3 as $word => $count): ?>
    <li><strong><?= htmlspecialchars($word) ?></strong> (<?= $count ?>)</li>
<?php endforeach; ?>
</ul>

<canvas id="trendChart" width="400" height="200"></canvas>

<script>
new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Keyword Frequency',
            data: <?= json_encode($data) ?>,
            borderWidth: 1
        }]
    },
    options: {
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

</body>
</html>