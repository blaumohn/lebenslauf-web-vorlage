import { constants, accessSync } from 'node:fs';
import process from 'node:process';
import { $, which } from 'zx';

export class A11yQa {
  static pages() {
    const langs = (process.env.CONTENT_LANGS || 'de')
      .split(',').map(l => l.trim()).filter(Boolean);
    const langPages = langs.map(l => ({ path: `/cv?lang=${l}`, name: `public CV ${l}` }));
    return [
      { path: '/', name: 'home' },
      ...langPages,
      { path: '/contact', name: 'contact form' }
    ];
  }

  static launchOptions() {
    const executablePath = this.resolveBrowser();
    return executablePath ? { executablePath } : undefined;
  }

  static async ensureBrowser() {
    const executablePath = this.resolveBrowser();
    if (executablePath) {
      process.stdout.write(`Playwright nutzt Systembrowser: ${executablePath}\n`);
      return;
    }

    await $`playwright install chromium`;
  }

  static resolveBrowser() {
    for (const command of this.browserCommands()) {
      const executable = this.resolveCommand(command);
      if (executable) {
        return executable;
      }
    }

    for (const candidate of this.browserPaths()) {
      if (this.isExecutable(candidate)) {
        return candidate;
      }
    }

    return null;
  }

  static browserCommands() {
    return [
      'chromium-browser',
      'chromium',
      'google-chrome',
      'google-chrome-stable'
    ];
  }

  static browserPaths() {
    return [
      '/usr/bin/chromium-browser',
      '/usr/bin/chromium',
      '/usr/bin/google-chrome',
      '/usr/bin/google-chrome-stable',
      '/Applications/Chromium.app/Contents/MacOS/Chromium',
      '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
    ];
  }

  static resolveCommand(command) {
    const executable = which.sync(command, { nothrow: true });

    return executable && this.isExecutable(executable)
      ? executable
      : null;
  }

  static isExecutable(path) {
    try {
      accessSync(path, constants.X_OK);
      return true;
    } catch {
      return false;
    }
  }
}
