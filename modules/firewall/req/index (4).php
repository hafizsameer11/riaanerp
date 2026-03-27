<?php
/******************************************************
 *  CREDENTIAL MANAGER – FINAL FIXED VERSION
 *  — PIN access (4683)
 *  — AES-256 encrypted passwords
 *  — SQLite database (credentials.db)
 *  — Safe JSON-based rendering (no JS string injection)
 *  — Dark/Light theme, search, show/hide password
 ******************************************************/

session_start();

define('PIN_CODE', '4683');
define('ENC_KEY_RAW', 'aa99aa90aa');

/******************************************************
 *  AES-256 KEY
 ******************************************************/
function enc_key() {
    static $k = null;
    if ($k === null) $k = hash("sha256", ENC_KEY_RAW, true);
    return $k;
}

function encryptPassword($plain) {
    if ($plain === "") return "";
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, "AES-256-CBC", enc_key(), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv).":".base64_encode($cipher);
}

function decryptPassword($stored) {
    if ($stored === "") return "";
    if (!str_contains($stored, ":")) return $stored;
    [$ivB64,$cB64] = explode(":", $stored, 2);
    $iv = base64_decode($ivB64);
    $c  = base64_decode($cB64);
    if (!$iv || !$c) return "[decode error]";
    $plain = openssl_decrypt($c,"AES-256-CBC",enc_key(),OPENSSL_RAW_DATA,$iv);
    return $plain ?: "[decode error]";
}

/******************************************************
 *  SQLITE SETUP
 ******************************************************/
$db = new PDO("sqlite:".__DIR__."/credentials.db");
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE IF NOT EXISTS credentials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    description TEXT NOT NULL,
    url TEXT NOT NULL,
    username TEXT NOT NULL,
    password TEXT NOT NULL
)");

/******************************************************
 *  HANDLE PIN SUBMIT
 ******************************************************/
if (isset($_POST["pin"])) {
    if ($_POST["pin"]===PIN_CODE) {
        $_SESSION["auth"]=true;
        header("Location: passwords.php");
        exit;
    }
    $pin_error="Incorrect PIN";
}

/******************************************************
 *  BLOCK API IF NOT LOGGED IN
 ******************************************************/
function needAuth() {
    if (empty($_SESSION["auth"])) {
        http_response_code(403);
        exit("NO ACCESS");
    }
}

/******************************************************
 *  AJAX API
 ******************************************************/
$action=$_GET["action"]??"";

if ($action!=="") {
    needAuth();

    if ($action==="list") {
        $rows = $db->query("SELECT * FROM credentials ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) $r["password"]=decryptPassword($r["password"]);
        header("Content-Type: application/json");
        echo json_encode($rows);
        exit;
    }

    if ($action==="add") {
        $s=$db->prepare("INSERT INTO credentials(description,url,username,password) VALUES(?,?,?,?)");
        $s->execute([$_POST["description"],$_POST["url"],$_POST["username"],encryptPassword($_POST["password"])]);
        exit("OK");
    }

    if ($action==="update") {
        $s=$db->prepare("UPDATE credentials SET description=?,url=?,username=?,password=? WHERE id=?");
        $s->execute([$_POST["description"],$_POST["url"],$_POST["username"],encryptPassword($_POST["password"]),$_POST["id"]]);
        exit("OK");
    }

    if ($action==="delete") {
        $s=$db->prepare("DELETE FROM credentials WHERE id=?");
        $s->execute([$_POST["id"]]);
        exit("OK");
    }

    exit("BAD REQUEST");
}


/******************************************************
 *  PIN SCREEN IF NOT AUTH
 ******************************************************/
if (empty($_SESSION["auth"])) : ?>
<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Enter PIN</title>
<style>
body{background:#001a66;color:#fff;font-family:Arial;
display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
.box{background:#fff;color:#001a66;padding:30px;border-radius:10px;width:320px;text-align:center;}
input{width:100%;padding:12px;font-size:18px;margin:15px 0;border-radius:6px;border:1px solid #ccc;}
button{width:100%;padding:12px;font-size:17px;background:#001a66;color:#fff;border:none;border-radius:6px;cursor:pointer;}
.error{color:red;min-height:22px;}
img{max-height:60px;margin-bottom:10px;}
</style></head><body>
<div class="box">
<img src="logo.jpg" onerror="this.style.display='none'">
<h2>Enter PIN</h2>
<div class="error"><?= $pin_error??"" ?></div>
<form method="post">
<input type="password" name="pin" placeholder="PIN" autofocus>
<button>Unlock</button>
</form>
</div>
</body></html>
<?php exit; endif; ?>


<!DOCTYPE html>
<html>
<head><meta charset="utf-8">
<title>Credential Manager</title>

<style>
/************* THEME *************/
:root {
 --bg:#fff; --card:#f8faff; --text:#001a66;
 --border:#d3d9ff; --button:#001a66; --button-hover:#0033cc;
 --header:#001a66; --input:#fff; --link:#0033cc;
}

body.dark-mode{
 --bg:#0d0d14; --card:#191929; --text:#d3ddff;
 --border:#33334a; --button:#3045ff; --button-hover:#5366ff;
 --header:#14172b; --input:#1c1c2e; --link:#77aaff;
}

body{background:var(--bg);color:var(--text);font-family:Arial;margin:0;}
header{background:var(--header);color:#fff;padding:20px;position:relative;}
header h1{margin:0;}
header img{position:absolute;right:20px;top:10px;height:50px;}
.theme-toggle{position:absolute;right:130px;top:18px;background:var(--button);
color:#fff;border:none;padding:8px 14px;border-radius:20px;cursor:pointer;}

.container{background:var(--card);padding:20px;border:1px solid var(--border);
width:90%;max-width:1100px;margin:25px auto;border-radius:12px;}

input[type=text],input[type=search]{
width:100%;padding:10px;margin-bottom:12px;
background:var(--input);color:var(--text);
border:1px solid var(--border);border-radius:6px;}

button{padding:10px 20px;border:none;border-radius:6px;
background:var(--button);color:#fff;cursor:pointer;margin-right:5px;}
button:hover{background:var(--button-hover);}
.small{padding:6px 10px;font-size:12px;}
.del{background:#c00;} .del:hover{background:#a00;}

.table-wrap{max-height:400px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;}
table{width:100%;border-collapse:collapse;}
th{background:var(--header);color:#fff;padding:12px;position:sticky;top:0;}
td{padding:10px;border-bottom:1px solid var(--border);}
a{color:var(--link);}
</style>

<script>
function toggleTheme(){
 document.body.classList.toggle("dark-mode");
 localStorage.setItem("passTheme",document.body.classList.contains("dark-mode")?"dark":"light");
}
function applyTheme(){
 if(localStorage.getItem("passTheme")==="dark") document.body.classList.add("dark-mode");
}

/************* LOAD ENTRIES SAFELY *************/
function loadEntries() {
 fetch("passwords.php?action=list")
 .then(r=>r.json())
 .then(rows=>{
    const s = document.getElementById("search").value.toLowerCase();
    const tbody = document.getElementById("list");
    tbody.innerHTML = "";

    rows.filter(r=>r.description.toLowerCase().includes(s))
        .forEach(r=>{
            let tr=document.createElement("tr");

            tr.innerHTML = `
              <td>${r.description}</td>
              <td><a href="${r.url}" target="_blank">${r.url}</a></td>
              <td>${r.username}</td>
              <td>
                  <span class="pwd" data-pwd="${r.password}" data-show="0">••••••••</span>
                  <button class="small" onclick="togglePwd(this)">Show</button>
              </td>
              <td>
                  <button class="small" onclick='editEntry(${r.id})'>Edit</button>
                  <button class="small del" onclick='deleteEntry(${r.id})'>Delete</button>
              </td>
            `;

            tbody.appendChild(tr);
        });
 });
}

function togglePwd(btn){
 const span = btn.previousElementSibling;
 if(span.dataset.show==="0"){
    span.textContent = span.dataset.pwd;
    span.dataset.show="1";
    btn.textContent="Hide";
 } else {
    span.textContent="••••••••";
    span.dataset.show="0";
    btn.textContent="Show";
 }
}

let editId=null;

function editEntry(id){
 fetch("passwords.php?action=list")
 .then(r=>r.json())
 .then(rows=>{
    const r = rows.find(x=>x.id==id);
    if(!r) return;

    editId=id;

    document.getElementById("desc").value=r.description;
    document.getElementById("url").value=r.url;
    document.getElementById("user").value=r.username;
    document.getElementById("pass").value=r.password;
 });
}

function saveEntry(){
 let fd=new FormData();
 fd.append("description",desc.value);
 fd.append("url",url.value);
 fd.append("username",user.value);
 fd.append("password",pass.value);

 let a="add";
 if(editId){ fd.append("id",editId); a="update"; }

 fetch("passwords.php?action="+a,{method:"POST",body:fd})
 .then(()=>{ editId=null; clearForm(); loadEntries(); });
}

function deleteEntry(id){
 if(!confirm("Delete this entry?")) return;
 let fd=new FormData(); fd.append("id",id);
 fetch("passwords.php?action=delete",{method:"POST",body:fd})
 .then(()=>loadEntries());
}

function clearForm(){
 editId=null;
 desc.value=""; url.value=""; user.value=""; pass.value="";
}

window.onload=()=>{applyTheme();loadEntries();}
</script>

</head>
<body>

<header>
<h1>Credential Manager</h1>
<button class="theme-toggle" onclick="toggleTheme()">Toggle Theme</button>
<img src="logo.jpg" onerror="this.style.display='none'">
</header>

<div class="container">

<input type="search" id="search" placeholder="Search description..." oninput="loadEntries()">

<h2>Saved Entries</h2>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>Description</th><th>URL</th><th>Username</th><th>Password</th><th>Actions</th>
</tr>
</thead>
<tbody id="list"></tbody>
</table>
</div>

<hr>

<h2>Add / Edit Entry</h2>

<label>Description</label>
<input type="text" id="desc">

<label>URL</label>
<input type="text" id="url">

<label>Username</label>
<input type="text" id="user">

<label>Password</label>
<input type="text" id="pass">

<button onclick="saveEntry()">Save</button>
<button onclick="clearForm()">Clear</button>

</div>
</body>
</html>
