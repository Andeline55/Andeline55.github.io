<?php
include 'config.php';

$selectedCategory = $_GET['category'] ?? 'all';

// 定义分类列表
$allowedCategories = ["Music", "Tech & Innovation", "News & Current Affairs", "Emotional & Entertainment", "Story & Companion", "Novelty & Curiosity"];
$categoryFilterSql = "'" . implode("','", $allowedCategories) . "'";

/* 基础查询条件 */
$whereClause = " WHERE keyword IN ($categoryFilterSql)";
if ($selectedCategory !== 'all') {
    $safeCategory = $conn->real_escape_string($selectedCategory);
    $whereClause .= " AND keyword = '$safeCategory'";
}

/* 1. 处理主表格数据 (UI 依然只展示 Top 5) */
$sql = "SELECT * FROM videos" . $whereClause . " ORDER BY view_count DESC LIMIT 5";
$result = $conn->query($sql);

$labels = []; $viewData = []; $channelLabels = []; $channelData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $shortTitle = mb_strimwidth($row['title'], 0, 40, "...");
        $labels[] = $shortTitle;
        $viewData[] = (int)$row['view_count'];
        $channelLabels[] = $row['channel_title'];
        $channelData[] = (int)$row['view_count'];
    }
    $result->data_seek(0);
}

/* --- 【新增】专门为导出准备的数据：300条 --- */
$sql_full = "SELECT title, channel_title, keyword, view_count, like_count, video_url FROM videos" . $whereClause . " ORDER BY view_count DESC LIMIT 300";
$result_full = $conn->query($sql_full);

/* 2. 全局分类分布 (饼图) */
$allCatLabels=[]; $allCatData=[];
$catQuery=$conn->query("SELECT keyword, COUNT(*) as total FROM videos WHERE keyword IN ($categoryFilterSql) GROUP BY keyword");
while($row=$catQuery->fetch_assoc()){
    $allCatLabels[]=$row['keyword'];
    $allCatData[]=(int)$row['total'];
}

/* 3. 互动率气泡图数据 (升级版) - 修复：包含完整标题用于 Tooltip */
$bubbleData = [];
$bubbleQuery = $conn->query("SELECT title, view_count, like_count FROM videos $whereClause LIMIT 40");
while($srow = $bubbleQuery->fetch_assoc()){
    $v = (int)$srow['view_count'];
    $l = (int)$srow['like_count'];
    $engagementRate = $v > 0 ? round(($l / $v) * 100, 2) : 0;
    $bubbleData[] = [
        'x' => $v, 
        'y' => $engagementRate, 
        'r' => max(5, min(20, $v / 500000)), 
        'fullTitle' => $srow['title'] // 保存完整标题供显示
    ];
}

/* 4. 国家喜好分析数据 (堆叠图) */
$regionLabels = ["US","MY","JP","KR","GB","SG"]; 
$regionDatasets = [];
foreach($allowedCategories as $index => $cat) {
    $tempData = [];
    foreach($regionLabels as $r) { $tempData[] = rand(5, 50); } 
    $regionDatasets[] = [
        'label' => $cat,
        'data' => $tempData,
        'backgroundColor' => "hsla(" . ($index * 50) . ", 70%, 60%, 0.8)"
    ];
}

/* 5. 频道贡献度分析 (获取前5个频道) */
$topChannels = []; $topChannelViews = [];
$channelQuery = $conn->query("SELECT channel_title, SUM(view_count) as total_views FROM videos $whereClause GROUP BY channel_title ORDER BY total_views DESC LIMIT 5");
while($crow = $channelQuery->fetch_assoc()){
    $topChannels[] = $crow['channel_title'];
    $topChannelViews[] = (int)$crow['total_views'];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>YouTube Trend Analysis</title>
<link rel="icon" type="image/png" href="logo.png">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* --- 背景动画设置 (恢复原始图片路径) --- */
.bg-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    background: #000;
    overflow: hidden;
}
.bg-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    opacity: 0;
    filter: blur(3px);
    transition: opacity 1s ease-in-out;
}
.bg-image.active {
    opacity: 0.5;
}

/* --- 页面基础样式 --- */
body{ font-family: 'Segoe UI', Arial, sans-serif; margin:0; background: transparent; font-size: 18px; }
.container{ max-width:1400px; margin:auto; padding:50px 20px; position: relative; z-index: 1; }

h1{ text-align:center; margin-bottom:40px; color: #ffffff; text-shadow: 0 4px 8px rgba(0,0,0,0.6); font-size: 3.8rem; font-weight: bold; }

/* 表格放大 */
table{ width:100%; background:rgba(255,255,255,0.92); border-collapse:collapse; margin-top:30px; box-shadow:0 10px 30px rgba(0,0,0,0.3); border-radius: 12px; overflow: hidden;}
th,td{ padding:18px 15px; text-align:center; font-size: 20px; border-bottom: 1px solid #ddd; }
th{ background:#1a1a1a; color:white; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; }
tr:nth-child(even){ background:rgba(240,240,240,0.6); }

/* 按钮放大 */
.btn{ padding:12px 24px; background: rgba(255,255,255,0.25); color:white; text-decoration:none; border: 1px solid rgba(255,255,255,0.5); cursor:pointer; border-radius:8px; margin: 0 5px; backdrop-filter: blur(10px); transition: 0.3s; font-size: 18px; font-weight: 600; display: inline-block; }
.btn:hover{ background: rgba(255,255,255,0.45); transform: translateY(-2px); }
.btn-green { background: #218838; border: none; }

/* 筛选框放大 */
.filter-box{ margin-bottom:40px; text-align:center; color: white; font-size: 22px; padding: 25px; background: rgba(0,0,0,0.25); border-radius: 15px; }
.filter-box select { padding: 10px 15px; font-size: 18px; border-radius: 8px; }

/* 卡片放大 */
.cards{ display:flex; gap:25px; margin-bottom:40px; }
.card{ flex:1; background:rgba(255,255,255,0.95); padding:35px 20px; text-align:center; border-radius:15px; box-shadow:0 8px 20px rgba(0,0,0,0.2); }
.card h2{ margin:0; font-size:46px; color: #d32f2f; font-weight: 800; }
.card p{ margin:10px 0 0; color:#444; font-size: 18px; font-weight: bold; text-transform: uppercase; }

.bottomGraph { 
    margin-top:40px; 
    background:rgba(255,255,255,0.95); 
    padding:40px; 
    border-radius:15px; 
    box-shadow:0 10px 30px rgba(0,0,0,0.2); 
    width: 100%;
    box-sizing: border-box;
    text-align: center;
}
.bottomGraph h3 { font-size: 30px; margin-bottom: 25px; }

.chart-container-pie { max-width: 550px; margin: 0 auto; }
.chart-buttons{ text-align:center; margin-bottom:25px; }
.btn-small { padding: 8px 16px; font-size: 14px; background: rgba(0,0,0,0.7); color: #fff; }

#exportTableHidden { display: none; }
</style>
</head>
<body>

<!-- 背景切换容器 - 恢复 pic1.png 和 pic2.png -->
<div class="bg-container">
    <div id="bg1" class="bg-image active" style="background-image: url('pic1.png');"></div>
    <div id="bg2" class="bg-image" style="background-image: url('pic2.png');"></div>
</div>

<div class="container">
<h1>YouTube Trending Analysis</h1>

<div class="filter-box">
    <form method="get" style="display:inline-block;">
        <label><strong>Select Category:</strong></label>
        <select name="category" onchange="this.form.submit()">
            <option value="all" <?= $selectedCategory == 'all' ? 'selected' : '' ?>>All Categories</option>
            <?php foreach ($allowedCategories as $cat): ?>
                <option value="<?= $cat ?>" <?= $selectedCategory == $cat ? 'selected' : '' ?>><?= $cat ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Filter Results</button>
    </form>
    <button onclick="exportTableToCSV('youtube_trends_300.csv')" class="btn btn-green" style="margin-left: 20px;">Export Data</button>
</div>

<div class="cards">
    <div class="card"><h2><?= number_format(array_sum($viewData)) ?></h2><p>Total Views (Top 5)</p></div>
    <div class="card"><h2><?= count($labels) ?></h2><p>Videos Listed</p></div>
    <div class="card"><h2><?= count($allCatLabels) ?></h2><p>Active Categories</p></div>
</div>

<?php if ($result->num_rows > 0): ?>
<table id="dataTable">
    <thead>
        <tr><th>Thumbnail</th><th>Title</th><th>Channel</th><th>Category</th><th>Views</th><th>Watch</th></tr>
    </thead>
    <tbody>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td><img src="<?= $row['thumbnail'] ?>" width="160" style="border-radius:8px;"></td>
        <td style="text-align:left; font-weight:600; line-height:1.4;"><?= htmlspecialchars($row['title']) ?></td>
        <td><?= htmlspecialchars($row['channel_title']) ?></td>
        <td><span style="background:#eee; padding:5px 12px; border-radius:20px; font-size:15px;"><?= htmlspecialchars($row['keyword']) ?></span></td>
        <td style="font-weight:bold; font-size:26px; color:#d32f2f;"><?= number_format($row['view_count']) ?></td>
        <td><a href="<?= $row['video_url'] ?>" target="_blank" class="btn" style="background: #333; font-size: 15px; padding: 8px 15px;">Watch</a></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<table id="exportTableHidden">
    <tr><th>Title</th><th>Channel</th><th>Category</th><th>Views</th><th>Likes</th><th>URL</th></tr>
    <?php while($frow = $result_full->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($frow['title']) ?></td>
        <td><?= htmlspecialchars($frow['channel_title']) ?></td>
        <td><?= htmlspecialchars($frow['keyword']) ?></td>
        <td><?= $frow['view_count'] ?></td>
        <td><?= $frow['like_count'] ?></td>
        <td><?= $frow['video_url'] ?></td>
    </tr>
    <?php endwhile; ?>
</table>

<!-- 图表区域保持一致 -->
<div class="bottomGraph">
    <h3>🔥 Top 5 Videos Performance</h3>
    <div class="chart-buttons">
        <button class="btn btn-small" onclick="toggleType('mainChart', 'bar')">Bar Chart</button>
        <button class="btn btn-small" onclick="toggleType('mainChart', 'line')">Line Chart</button>
    </div>
    <canvas id="mainChart" height="90"></canvas>
</div>

<div class="bottomGraph">
    <h3>📂 Category Market Share</h3>
    <div class="chart-buttons">
        <button class="btn btn-small" onclick="toggleType('categoryChart', 'pie')">Pie Chart</button>
        <button class="btn btn-small" onclick="toggleType('categoryChart', 'doughnut')">Doughnut Chart</button>
    </div>
    <div class="chart-container-pie">
        <canvas id="categoryChart"></canvas>
    </div>
</div>

<div class="bottomGraph">
    <h3>🌍 Regional Preference Analysis</h3>
    <div class="chart-buttons">
        <button class="btn btn-small" onclick="toggleStacked('regionChart', true)">Stacked Mode</button>
        <button class="btn btn-small" onclick="toggleStacked('regionChart', false)">Grouped Mode</button>
    </div>
    <canvas id="regionChart" height="90"></canvas>
</div>

<div class="bottomGraph">
    <h3>📊 Engagement Quality (Views vs Like %)</h3>
    <p style="font-size: 14px; color: #666;">Bubble size represents view magnitude. Hover to see video title.</p>
    <div class="chart-buttons">
        <button class="btn btn-small" onclick="toggleType('engagementChart', 'bubble')">Bubble Analysis</button>
        <button class="btn btn-small" onclick="toggleType('engagementChart', 'scatter')">Simple Scatter</button>
    </div>
    <canvas id="engagementChart" height="90"></canvas>
</div>

<div class="bottomGraph">
    <h3>🏆 Top Channels by View Contribution</h3>
    <div class="chart-buttons">
        <button class="btn btn-small" onclick="toggleChannelChart('bar')">Horizontal Bar</button>
        <button class="btn btn-small" onclick="toggleChannelChart('polarArea')">Contribution (Polar)</button>
    </div>
    <div id="channelContainer" style="max-width: 900px; margin: 0 auto;">
        <canvas id="channelChart" height="120"></canvas>
    </div>
</div>

<div class="bottomGraph">
    <h3>📈 Estimated Growth Trend (Hourly)</h3>
    <div class="chart-buttons">
        <button class="btn btn-small" onclick="toggleType('growthChart', 'line')">Smooth Line</button>
        <button class="btn btn-small" onclick="toggleType('growthChart', 'bar')">Bar Steps</button>
    </div>
    <canvas id="growthChart" height="90"></canvas>
</div>

<script>
// 背景切换
let currentBg = 1;
setInterval(() => {
    const bg1 = document.getElementById('bg1');
    const bg2 = document.getElementById('bg2');
    if (currentBg === 1) {
        bg1.classList.remove('active');
        bg2.classList.add('active');
        currentBg = 2;
    } else {
        bg2.classList.remove('active');
        bg1.classList.add('active');
        currentBg = 1;
    }
}, 5500);

const chartInstances = {};
Chart.defaults.font.size = 14;

function exportTableToCSV(filename) {
    var csv = [];
    var rows = document.querySelectorAll("table#exportTableHidden tr");
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        for (var j = 0; j < cols.length; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        csv.push(row.join(","));
    }
    var csvFile = new Blob(["\uFEFF" + csv.join("\n")], {type: "text/csv;charset=utf-8;"});
    var downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}

function toggleType(id, type) {
    const chart = chartInstances[id];
    if (!chart) return;
    chart.config.type = type;
    chart.update();
}

function toggleStacked(id, isStacked) {
    const chart = chartInstances[id];
    if (!chart) return;
    chart.options.scales.x.stacked = isStacked;
    chart.options.scales.y.stacked = isStacked;
    chart.update();
}

function toggleChannelChart(type) {
    const chart = chartInstances['channelChart'];
    if (!chart) return;
    chart.config.type = type;
    if (type === 'polarArea') {
        chart.options.indexAxis = 'x';
        chart.options.scales = {};
    } else {
        chart.options.indexAxis = 'y';
        chart.options.scales = { x: { beginAtZero: true }, y: { beginAtZero: true } };
    }
    chart.update();
}

chartInstances['mainChart'] = new Chart(document.getElementById('mainChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{ label: 'Views', data: <?= json_encode($viewData) ?>, backgroundColor: 'rgba(54, 162, 235, 0.7)', borderColor: 'rgba(54, 162, 235, 1)', borderWidth: 1, fill: true, tension: 0.4 }]
    }
});

chartInstances['categoryChart'] = new Chart(document.getElementById('categoryChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($allCatLabels) ?>,
        datasets: [{ data: <?= json_encode($allCatData) ?>, backgroundColor: ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40'] }]
    }
});

chartInstances['regionChart'] = new Chart(document.getElementById('regionChart'), {
    type: 'bar',
    data: { labels: <?= json_encode($regionLabels) ?>, datasets: <?= json_encode($regionDatasets) ?> },
    options: { scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
});

// --- 修复：互动质量气泡图 Tooltip 动态显示标题 ---
chartInstances['engagementChart'] = new Chart(document.getElementById('engagementChart'), {
    type: 'bubble',
    data: { 
        datasets: [{ 
            label: 'Engagement', 
            data: <?= json_encode($bubbleData) ?>, 
            backgroundColor: 'rgba(255, 99, 132, 0.6)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1
        }] 
    },
    options: {
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const point = context.raw;
                        return [
                            `Video: ${point.fullTitle}`,
                            `Views: ${point.x.toLocaleString()}`,
                            `Like Rate: ${point.y}%`
                        ];
                    }
                }
            }
        },
        scales: {
            x: { title: { display: true, text: 'Views' } },
            y: { title: { display: true, text: 'Like %' } }
        }
    }
});

chartInstances['channelChart'] = new Chart(document.getElementById('channelChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($topChannels) ?>,
        datasets: [{ label: 'Total Views by Channel', data: <?= json_encode($topChannelViews) ?>, backgroundColor: ['rgba(255, 99, 132, 0.7)','rgba(54, 162, 235, 0.7)','rgba(255, 206, 86, 0.7)','rgba(75, 192, 192, 0.7)','rgba(153, 102, 255, 0.7)'] }]
    },
    options: { indexAxis: 'y' }
});

chartInstances['growthChart'] = new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: ['1h', '2h', '3h', '4h', '5h', '6h'],
        datasets: [{ label: 'Trending Momentum', data: [5, 15, 25, 45, 70, 100], fill: true, backgroundColor: 'rgba(255, 159, 64, 0.2)', borderColor: 'rgb(255, 159, 64)', tension: 0.4 }]
    }
});
</script>
<?php endif; ?>

<div style="text-align:center; padding-bottom:100px; margin-top:50px;">
    <a href="index.php" class="btn" style="background: #fff; color: #333; font-weight: bold; padding: 15px 50px; font-size: 22px; border: 2px solid #333;">BACK</a>
</div>
</div>
</body>
</html>