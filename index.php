<!DOCTYPE html
<html>
<head>
    <title>YouTube Trend Analysis System</title>

    <link rel="icon" type="image/png" href="logo.png">
    <style>

        body{
            margin:0;
            font-family:Arial, sans-serif;
            background:linear-gradient(to right,#1f1f1f,#3a3a3a);
            color:white;
        }

        .container{
            text-align:center;
            padding:100px 20px;
        }

        h1{
            font-size:42px;
            margin-bottom:20px;
        }

        p{
            font-size:18px;
            max-width:800px;
            margin:auto;
            line-height:1.6;
        }

        .btn-group{
            margin-top:40px;
        }

        .btn{
            display:inline-block;
            padding:15px 25px;
            margin:10px;
            background:#ff0000;
            color:white;
            text-decoration:none;
            font-size:16px;
            border-radius:5px;
            border:none;
            cursor:pointer;
            transition:0.3s;
        }

        .btn:hover{
            background:#cc0000;
        }

        .section{
            background:white;
            color:black;
            padding:60px 20px;
            text-align:center;
        }

        .feature-box{
            display:inline-block;
            width:250px;
            margin:20px;
        }

        .feature-box h3{
            margin-bottom:10px;
        }

        footer{
            background:#111;
            padding:20px;
            text-align:center;
            font-size:14px;
        }

        .update-box{
            position:fixed;
            top:20px;
            left:50%;
            transform:translateX(-50%);
            background:#4CAF50;
            color:white;
            padding:12px 25px;
            border-radius:5px;
            display:none;
            font-size:15px;
        }

    </style>

</head>

<body>

<div id="updateMessage" class="update-box">
Trending data updated successfully ✔
</div>

<div class="container">

<h1>YouTube Trend Analysis System</h1>

<p>
This web-based system automatically retrieves trending YouTube videos 
using the official YouTube Data API. It stores structured metadata 
in a relational database and applies rule-based classification 
to analyse content categories.
<br><br>
The system visualises the top-performing videos through interactive 
charts and allows users to filter videos by category.
</p>

<div class="btn-group">

<button class="btn" onclick="updateTrending()">
Update Trending Data
</button>

<a href="trending.php" class="btn">
View Top Trending Videos
</a>

</div>

</div>


<div class="section">

<h2>System Features</h2>

<div class="feature-box">
<h3>Data Collection</h3>
<p>Automatically retrieves trending videos via YouTube API.</p>
</div>

<div class="feature-box">
<h3>Data Classification</h3>
<p>Applies rule-based content categorisation to video titles.</p>
</div>

<div class="feature-box">
<h3>Data Visualisation</h3>
<p>Displays performance comparison using bar and pie charts.</p>
</div>

<div class="feature-box">
<h3>User Filtering</h3>
<p>Allows category-based filtering for targeted analysis.</p>
</div>

</div>


<footer>
Final Year Project | YouTube Trend Analysis System | 2026 | Andeline
</footer>


<script>

function updateTrending(){

fetch("fetch_trending.php")
.then(response => response.text())
.then(data => {

let box = document.getElementById("updateMessage");

box.style.display = "block";

setTimeout(function(){
box.style.display = "none";
},3000);

});

}

/* 页面打开时自动抓一次 */

updateTrending();

/* 每分钟自动抓一次 */

setInterval(function(){

updateTrending();

},60000);

</script>

</body>
</html>