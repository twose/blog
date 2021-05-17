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
    file_put_contents(($path = strtolower("./_/{$article['slug']}.md")), $article['raw']);
    $catalog[] = sprintf("| [%s](%s) | %s |", $article['title'], $path, explode('T', $article['date'])[0]);
}
$catalog = implode("\n", $catalog);
$readme = preg_replace('/(这里是可爱的目录开始\n)([\s\S]*)(\n这里是可爱的目录结束)/', "\$1\n{$catalog}\n\$3", file_get_contents(__DIR__ . '/README.md'));
file_put_contents(__DIR__ . '/README.md', $readme);
