<?php

declare(strict_types=1);

/** Match the native IPS build snapshot; installable resources come from IPS. */
$root = \dirname(__DIR__);
$read = static function (string $name) use ($root): array {
    $file = $root . '/data/' . $name . '.json';

    return is_file($file) ? json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : [];
};
$xml = new XMLWriter();
$xml->openMemory();
$xml->setIndent(true);
$xml->startDocument('1.0', 'UTF-8');
$xml->startElement('build');
$write = static function (string $element, string $key, array $value) use ($xml): void {
    $xml->startElement($element);
    $xml->writeAttribute('key', $key);
    $xml->writeCdata(str_replace(']]>', ']]]]><![CDATA[>', json_encode($value, JSON_THROW_ON_ERROR)));
    $xml->endElement();
};
foreach ($read('modules') as $location => $modules) {
    foreach ($modules as $key => $value) {
        $write('module', $location . '/' . $key, [
            'default_controller' => $value['default_controller'],
            'protected' => $value['protected'],
            'default' => $value['default'] ?? null,
        ]);
    }
}
foreach ($read('tasks') as $key => $frequency) {
    $xml->startElement('task');
    $xml->writeAttribute('frequency', $frequency);
    $xml->text($key);
    $xml->endElement();
}
foreach ($read('settings') as $value) {
    $write('setting', $value['key'], $value);
}
foreach (['widgets' => 'widget', 'acpsearch' => 'acpsearch', 'hooks' => 'hook', 'themesettings' => 'themesetting'] as $file => $element) {
    foreach ($read($file) as $key => $value) {
        $write($element, (string) $key, $value);
    }
}
$xml->endElement();
file_put_contents($root . '/data/build.xml', $xml->outputMemory());
echo "Wrote data/build.xml\n";
