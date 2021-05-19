<?php
$articles = json_decode(file_get_contents(__DIR__ . '/../twose.github.io/db.json'), true)['models']['Post'];
$articles = (function ($array, $key) {
    $keys = [];
    foreach ($array as $_key => $_value) {
        $keys[$_key] = $_value[$key];
    }
    array_multisort($keys, SORT_DESC, $array);
    return $array;
})($articles, 'date');
$catalog = ['| 主题 | 发布时间 |', '| ---- | ---- |'];
foreach ($articles as $article) {
    if (preg_match_all('/\!\[([\w.]+)\]\(((?i)\b(?:(?:https?:\/\/|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|(?:(?:[^\s()<>]+|(?:(?:[^\s()<>]+)))*))+(?:(?:(?:[^\s()<>]+|(?:(?:[^\s()<>]+)))*)|[^\s`!()[]{};:\'\".,<>?«»“”‘’])))\)/',
            $article['raw'], $images, PREG_SET_ORDER) > 0) {
        foreach ($images as $id => [$_, $imageTag, $imageUri]) {
            $imagePath = sprintf('images/%s-%d.%s', $article['slug'], $id, pathinfo($imageUri, PATHINFO_EXTENSION));
            $imageAbsolutePath = __DIR__ . "/_/{$imagePath}";
            if (!file_exists($imageAbsolutePath)) {
                echo "Downloading {$imageUri} to {$imagePath} in <{$article['title']}>({$article['slug']}.md)... ";
                if (!($imageContent = file_get_contents($imageUri, false, stream_context_create(['http' => ['timeout' => 10], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false,]])))) {
                    echo 'Failed' . PHP_EOL;
                    continue;
                }
                file_put_contents($imageAbsolutePath, $imageContent);
                echo 'Done' . PHP_EOL;
            }
            $article['raw'] = str_replace($imageUri, $imagePath, $article['raw']); // use local image
        }
    }
    file_put_contents(($path = strtolower("./_/{$article['slug']}.md")), $article['raw']);
    $catalog[] = sprintf("| [%s](%s) | %s |", $article['title'], $path, explode('T', $article['date'])[0]);
}
$catalog = implode("\n", $catalog);
$readme = preg_replace('/(这里是可爱的目录开始\n)([\s\S]*)(\n这里是可爱的目录结束)/', "\$1\n{$catalog}\n\$3", file_get_contents(__DIR__ . '/README.md'));
file_put_contents(__DIR__ . '/README.md', $readme);
