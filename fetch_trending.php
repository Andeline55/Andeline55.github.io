<?php
/**
 * YouTube 数据抓取工具 - 手动触发版
 * 功能：点击按钮后抓取指定区域的趋势视频并存入数据库
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Kuala_Lumpur');
include 'config.php';

$message = "";
$status_type = "info";

// 检查是否点击了抓取按钮
if (isset($_POST['fetch_data'])) {
    /* =================================
       1. YouTube API 配置
    ================================= */
    $apiKey = "AIzaSyArPLAIggJ7un5M89HQbsG3bxo56fLz8zM"; 
    $regions = ["US","MY","JP","KR","GB","CA","AU","SG","IN","DE","FR","BR","MX","ID","TH","PH"];
    $region = $_POST['selected_region'] ?? $regions[array_rand($regions)];

    $url = "https://www.googleapis.com/youtube/v3/videos?"
         . "part=snippet,statistics"
         . "&chart=mostPopular"
         . "&regionCode=" . $region
         . "&maxResults=50"
         . "&key=" . $apiKey;

    $response = @file_get_contents($url);
    
    if ($response === FALSE) {
        $message = "错误：无法连接 YouTube API，请检查 API Key 是否有效。";
        $status_type = "error";
    } else {
        $data = json_decode($response, true);

        if (!isset($data['items']) || empty($data['items'])) {
            $message = "在该区域 ($region) 未找到趋势视频。";
            $status_type = "warning";
        } else {
            /* =================================
               2. 核心处理循环
            ================================= */
            $newVideosAdded = 0;
            $skippedDuplicates = 0;

            foreach ($data['items'] as $item) {
                $video_id = $item['id'];
                $titleRaw = $item['snippet']['title'];
                $categoryId = $item['snippet']['categoryId'];

                $title = $conn->real_escape_string($titleRaw);
                $description = $conn->real_escape_string($item['snippet']['description']);
                $published_at = date("Y-m-d H:i:s", strtotime($item['snippet']['publishedAt']));
                $channel = $conn->real_escape_string($item['snippet']['channelTitle']);
                $views = $item['statistics']['viewCount'] ?? 0;
                $likes = $item['statistics']['likeCount'] ?? 0;
                $thumbnail = $item['snippet']['thumbnails']['medium']['url'];
                $video_url = "https://www.youtube.com/watch?v=" . $video_id;
                $fetched = date("Y-m-d H:i:s");

                /* --- 增强版分类算法 --- */
                $titleLower = strtolower($titleRaw);
                $keyword = "Emotional & Entertainment"; 

                if ($categoryId == "10") { $keyword = "Music"; } 
                elseif ($categoryId == "20") { $keyword = "Story & Companion"; }
                elseif ($categoryId == "25") { $keyword = "News & Current Affairs"; }
                elseif ($categoryId == "28" || preg_match('/iphone|android|ai|tech|launch|review/', $titleLower)) {
                    $keyword = (!preg_match('/game|gaming|playthrough|mod/', $titleLower)) ? "Tech & Innovation" : "Story & Companion";
                }
                else {
                    if (preg_match('/official music video|mv|lyrics|song/', $titleLower)) { $keyword = "Music"; }
                    elseif (preg_match('/news|breaking|government|official statement/', $titleLower)) { $keyword = "News & Current Affairs"; }
                    elseif (preg_match('/how to|tutorial|tips|guide|learn|explain|hack/', $titleLower)) { $keyword = "Novelty & Curiosity"; }
                    elseif (preg_match('/story|life|vlog|journey|family|experience|travel/', $titleLower)) { $keyword = "Story & Companion"; }
                    elseif (preg_match('/challenge|experiment|extreme|world record|insane|wow|amazing/', $titleLower)) { $keyword = "Novelty & Curiosity"; }
                    else { $keyword = "Emotional & Entertainment"; }
                }

                /* --- 数据库入库 --- */
                $check = $conn->query("SELECT id FROM videos WHERE video_id='$video_id'");
                if ($check->num_rows == 0) {
                    $sql = "INSERT INTO videos 
                            (video_id, title, description, published_at, channel_title, fetched_at, view_count, like_count, thumbnail, video_url, keyword) 
                            VALUES 
                            ('$video_id', '$title', '$description', '$published_at', '$channel', '$fetched', '$views', '$likes', '$thumbnail', '$video_url', '$keyword')";
                    if ($conn->query($sql)) { $newVideosAdded++; }
                } else {
                    $skippedDuplicates++;
                }
            }
            $message = "操作成功！区域: $region | 新增: $newVideosAdded | 跳过重复: $skippedDuplicates";
            $status_type = "success";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>YouTube 数据抓取控制台</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full">
        <h1 class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2">
            <span class="text-red-600">▶</span> YouTube 数据更新
        </h1>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg text-sm <?php 
                echo $status_type == 'success' ? 'bg-green-100 text-green-700' : 
                    ($status_type == 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'); 
            ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">选择抓取区域：</label>
                <select name="selected_region" class="w-full border-gray-300 rounded-md shadow-sm p-2 border">
                    <option value="MY">Malaysia (MY)</option>
                    <option value="US">United States (US)</option>
                    <option value="JP">Japan (JP)</option>
                    <option value="KR">Korea (KR)</option>
                    <option value="SG">Singapore (SG)</option>
                    <option value="GB">United Kingdom (GB)</option>
                </select>
            </div>

            <button type="submit" name="fetch_data" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200">
                立即抓取趋势数据
            </button>
        </form>

        <p class="mt-4 text-xs text-gray-400 text-center">
            点击按钮后将实时请求 YouTube API 并更新数据库。
        </p>
    </div>

</body>
</html>