import process from 'node:process';
import { $, fs } from 'zx';

const missingBuildMessage =
  'A11y-QA braucht einen Build: zuerst cli build dev ausführen.';

if (!fs.existsSync('var/config/config.json')) {
  process.stderr.write(`${missingBuildMessage}\n`);
  process.exit(1);
}

await $`php -S 127.0.0.1:8080 -t public scripts/php-dev-server-router.php`;
