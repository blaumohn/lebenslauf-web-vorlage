<?php

namespace App\Http\Task\Deploy;

use App\Http\Task\Task;
use Symfony\Component\Filesystem\Path;

final class DeployState
{
    private function __construct(
        private readonly string $deployId,
        private readonly DeploySlot $appSlot,
        private readonly DeploySlot $vendorSlot,
        private readonly string $vendorChecksum = '',
    ) {
        if ($deployId === '') {
            throw new \RuntimeException('Deploy-ID fehlt.');
        }
    }

    public static function fromTask(Task $task, string $entryPath): self
    {
        $app = DeploySlot::app($task->get('app'));
        $vendor = DeploySlot::vendor($task->get('vendor'));
        $checksum = self::readVendorMeta($entryPath, $vendor->directory());
        return new self($task->get('run_id'), $app, $vendor, $checksum);
    }

    public static function fromParams(string $deployId, string $app, string $vendor): self
    {
        return new self($deployId, DeploySlot::app($app), DeploySlot::vendor($vendor));
    }

    private static function readVendorMeta(string $entryPath, string $vendorDir): string
    {
        $path = Path::join($entryPath, $vendorDir, '.meta');
        if (!is_file($path)) {
            throw new \RuntimeException("Vendor-Sentinel fehlt: {$path}");
        }

        $data = parse_ini_file($path, true);
        $checksum = $data['vendor']['checksum'] ?? null;
        if (!is_string($checksum) || trim($checksum) === '') {
            throw new \RuntimeException("Vendor-Sentinel ungültig: {$path}");
        }
        return trim($checksum);
    }

    public function deployId(): string
    {
        return $this->deployId;
    }

    public function appLabel(): string
    {
        return $this->appSlot->label();
    }

    public function vendorLabel(): string
    {
        return $this->vendorSlot->label();
    }

    public function appRunMarkerPath(): string
    {
        return $this->appSlot->runMarkerPath();
    }

    public function validatePreparedSlots(string $entryPath): void
    {
        $this->validateAppSlot($entryPath);
        $this->validateVendorSlot($entryPath);
    }

    // deploy: Dieses Format wird von HtaccessSlotFile.read_slot() per Regex gelesen.
    // Änderung hier → _APP_SLOT_RE in src/cli/py/deploy/sftp_deploy_state.py anpassen.
    // Siehe: https://docs.template.ysdani.com/de/areas/deploy/slot-switch/
    public function toHtaccess(): string
    {
        $app = $this->appSlot->label();
        return "# deploy-slot: {$app}\n"
            . "RewriteEngine On\n"
            . "RewriteCond %{DOCUMENT_ROOT}/app-{$app}/public/%{REQUEST_URI} -f\n"
            . "RewriteRule ^(.*)$ /app-{$app}/public/\$1 [L]\n"
            . "RewriteRule ^ /app-{$app}/public/index.php [L,QSA]\n";
    }

    private function validateAppSlot(string $entryPath): void
    {
        $markerPath = $this->appRunMarkerPath();
        $stored = $this->readSentinel(Path::join($entryPath, $markerPath), "App-Sentinel");
        if ($stored !== $this->deployId) {
            throw new \RuntimeException("run_id stimmt nicht überein: " . dirname($markerPath));
        }
    }

    private function validateVendorSlot(string $entryPath): void
    {
        $path = Path::join($entryPath, $this->vendorSlot->directory(), '.meta');
        $this->readSentinel($path, "Vendor-Sentinel");
    }

    private function readSentinel(string $fullPath, string $label): string
    {
        if (!is_file($fullPath)) {
            throw new \RuntimeException("{$label} fehlt: {$fullPath}");
        }
        $content = trim((string) file_get_contents($fullPath));
        if ($content === '') {
            throw new \RuntimeException("{$label} leer: {$fullPath}");
        }
        return $content;
    }
}
