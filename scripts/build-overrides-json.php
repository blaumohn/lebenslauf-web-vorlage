<?php

declare(strict_types=1);

function get_set_nonempty(array $names): array
{
  $out = [];
  foreach ($names as $name) {
    $value = getenv($name);
    if ($value !== false && $value !== '') {
      $out[$name] = $value;
    }
  }
  return $out;
}

function build_overrides_json(): array
{
  return [
    'preview' => [
      'runtime' => [
        'smtp' => get_set_nonempty([
          'SMTP_PASS',
        ]),
      ],
      'deploy' => [
        'ftp' => get_set_nonempty([
          'FTP_HOST',
          'FTP_USER',
          'FTP_PASS',
          'FTP_PORT',
          'FTP_SERVER_DIR',
        ])
      ],
    ],
  ];
}

echo json_encode(
  build_overrides_json(),
  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
