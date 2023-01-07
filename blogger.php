<?php
// Classes Require
require_once(dirname(__FILE__) . '/classes/vendor/autoload.php');

// Classes Use
use Tectalic\OpenAi\Manager;
use Tectalic\OpenAi\Authentication;
use Tectalic\OpenAi\Models\Completions\CreateRequest as CreateRequestText;
use Tectalic\OpenAi\Models\ImagesGenerations\CreateRequest as CreateRequestImage;

// Database Connect
try {
    $DBH = new PDO('mysql:host=127.0.0.1;dbname=XXX', 'XXX', 'XXX');
    $DBH->exec('SET NAMES utf8mb4');
}
catch(PDOException $e) {
    //
}

// Client Generate
$client = Manager::build(new \GuzzleHttp\Client(), new Authentication('XXX'));

// Function Retrieve
function Retrieve($link) {
    $headers = array();

    $ch = curl_init();

    curl_setopt_array($ch, array(
        CURLOPT_URL => $link,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_ENCODING => 'gzip, deflate',
        CURLOPT_HTTPHEADER => $headers,
    ));

    $result = curl_exec($ch);

    curl_close($ch);

    return $result;
}

// Function Generate
function Generate($type, $prompt) {
    global $client;

    if($type == 'text') {
        $response = $client->completions()->create(
            new CreateRequestText([
                'prompt' => $prompt,
                'model'  => 'text-davinci-003',
                'max_tokens' => 2048,
            ])
        )->toModel();

        return trim($response->choices[0]->text);
    }

    if($type == 'image') {
        $response = $client->imagesGenerations()->create(
            new CreateRequestImage([
                'prompt' => $prompt,
                'size' => '1024x1024',
                'n' => 1,
            ])
        )->toModel();

        return trim($response->data[0]->url);
    }
}

// Function Host
function Host($file) {
    $access = Access();

    $fh = fopen($file, 'rb');

    $source = fread($fh, filesize($file));

    fclose($fh);

    $boundary = time();

    $data = '';
    $data .= '--' . $boundary . "\r\n";
    $data .= 'Content-Type: application/json; charset=UTF-8' . "\r\n" . "\r\n";
    $data .= json_encode(array('name' => basename($file), 'mimeType' => mime_content_type($file), 'parents' => array('XXX'))) . "\r\n";
    $data .= '--' . $boundary . "\r\n";
    $data .= 'Content-Transfer-Encoding: base64' . "\r\n" . "\r\n";
    $data .= base64_encode($source) . "\r\n";
    $data .= '--' . $boundary . '--';

    $ch = curl_init();

    curl_setopt_array($ch, array(
        CURLOPT_URL => 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart',
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_BINARYTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $access->access_token,
            'Content-Type: multipart/related; boundary=' . $boundary,
        ),
        CURLOPT_POST => TRUE,
        CURLOPT_POSTFIELDS => $data,
    ));

    $result = curl_exec($ch);

    curl_close($ch);

    return $result;
}

// Function Publish
function Publish($title, $content, $labels) {
    $access = Access();

    $ch = curl_init();

    curl_setopt_array($ch, array(
        CURLOPT_URL => 'https://www.googleapis.com/blogger/v3/blogs/XXX/posts/',
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $access->access_token,
            'Content-Type: application/json',
        ),
        CURLOPT_POST => TRUE,
        CURLOPT_POSTFIELDS => json_encode(array(
            'kind' => 'blogger#post',
            'blog' => array(
                'id' => 'XXX'
            ),
            'title' => $title,
            'content' => $content,
            'labels' => $labels,
        )),
    ));

    $result = curl_exec($ch);

    curl_close($ch);

    return $result;
}

// Function Access
function Access() {
    $result = json_decode(file_get_contents('https://oauth2.googleapis.com/token', FALSE, stream_context_create(array(
        'http' => array(
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'method'  => 'POST',
            'content' => http_build_query(array(
                'client_id' => 'XXX',
                'client_secret' => 'XXX',
                'refresh_token' => 'XXX',
                'grant_type' => 'refresh_token',
            )),
        ),
    ))));

    return $result;
}

// Function Refresh
function Refresh() {
    if(isset($_GET['code'])) {
        $result = json_decode(file_get_contents('https://oauth2.googleapis.com/token', FALSE, stream_context_create(array(
            'http' => array(
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'method'  => 'POST',
                'content' => http_build_query(array(
                    'code' => $_GET['code'],
                    'client_id' => 'XXX',
                    'client_secret' => 'XXX',
                    'redirect_uri' => 'https://XXX/blogger.php',
                    'grant_type' => 'authorization_code',
                )),
            ),
        ))));

        return $result;
    }
    else {
        header('Location: https://accounts.google.com/o/oauth2/auth?' . http_build_query(array(
            'response_type' => 'code',
            'client_id' => 'XXX',
            'redirect_uri' => 'https://XXX/blogger.php',
            'scope' => 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/blogger',
            'access_type' => 'offline',
        )));
    }
}

// Blogger Script
preg_match_all('/<item>(.*?)<\/item>/s', Retrieve('https://moxie.foxnews.com/google-publisher/latest.xml'), $items);

foreach($items[1] as $item) {
    // Blogger Array
    $blogger = array();

    // Blogger Link
    preg_match('/<guid isPermaLink="true">(.*?)<\/guid>/s', $item, $link);

    $blogger['link'] = $link[1];

    // Blogger Title
    preg_match('/<title>(.*?)<\/title>/s', $item, $title);

    $blogger['title'] = $title[1];

    // Blogger Content
    preg_match('/<content:encoded>(.*?)<\/content:encoded>/s', $item, $content);

    $blogger['content'] = strip_tags(preg_replace('/>CLICK HERE(.*?)<\/a>/s', '', htmlspecialchars_decode($content[1])));

    // Blogger Database
    $STH = $DBH->prepare("SELECT id FROM blogger WHERE link=:link LIMIT 0,1");
    $STH->execute(array(
        'link' => $blogger['link'],
    ));

    if($STH->rowCount() == 0) {
        // Blogger Database
        $STH = $DBH->prepare("INSERT INTO blogger (link) VALUES (:link)");
        $STH->execute(array(
            'link' => $blogger['link'],
        ));

        // Blogger Title
        $blogger['title'] = Generate('text', 'Rephrase this blog title: ' . $blogger['title']);

        // Blogger Image
        $blogger['image'] = Generate('image', $blogger['title']);

        // Blogger Content
        $blogger['content'] = nl2br(Generate('text', 'Rephrase this blog post in under 750 words: ' . $blogger['content']));

        // Blogger Labels
        $blogger['labels'] = array();

        foreach(explode(' ', Generate('text', 'Create hashtags from this blog title: ' . $blogger['title'])) as $label) {
            $blogger['labels'][] = trim(preg_replace('/[^A-Za-z0-9\-]/', '', $label));
        }

        // Blogger Check
        if(strlen($blogger['title']) > 10 && strlen($blogger['content']) > 10 && !empty($blogger['labels'])) {
            // Bloger Image
            file_put_contents(dirname(__FILE__) . '/blogger.png', file_get_contents($blogger['image']));

            $image = imagecreatefrompng(dirname(__FILE__) . '/blogger.png');
    
            imagejpeg($image, dirname(__FILE__) . '/blogger.jpg', 70);
    
            imagedestroy($image);
    
            $result = json_decode(Host(dirname(__FILE__) . '/blogger.png'));
    
            $blogger['image'] = 'https://drive.google.com/uc?id=' . $result->id;
    
            unlink(dirname(__FILE__) . '/blogger.png');
            unlink(dirname(__FILE__) . '/blogger.jpg');

            // Blogger Publish
            $blogger['publish'] = json_decode(Publish($blogger['title'], '<img src="' . $blogger['image'] . '" /><hr />' . $blogger['content'], $blogger['labels']));

            $blogger['publish'] = $blogger['publish']->id;

            // Blogger Database
            $STH = $DBH->prepare("UPDATE blogger SET publish=:publish, title=:title, image=:image, content=:content, labels=:labels WHERE link=:link LIMIT 1");
            $STH->execute(array(
                'publish' => $blogger['publish'],
                'link' => $blogger['link'],
                'title' => $blogger['title'],
                'image' => $blogger['image'],
                'content' => $blogger['content'],
                'labels' => implode(',', $blogger['labels']),
            ));
        }
    }
}
?>