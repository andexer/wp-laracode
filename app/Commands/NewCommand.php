<?php

namespace App\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class NewCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'new
        {name : The name of the plugin (e.g., my-awesome-plugin)}
        {--author= : The author name}
        {--author_email= : The author email}
        {--author_url= : The author URL}
        {--description= : The plugin description}
        {--namespace= : The root namespace (e.g., MyPlugin)}
        {--license=GPL-2.0-or-later : The license (GPL-2.0-or-later, MIT, etc.)}
        {--template=base : Template to use (base)}
        {--force : Overwrite existing files}';

	/**
	 * Available templates.
	 *
	 * @var array
	 */
	protected array $availableTemplates = ['base'];

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new isolated WordPress plugin using a modern Laravel stack.';

	/** @var Filesystem */
	protected $filesystem;

	public function __construct(Filesystem $filesystem)
	{
		parent::__construct();
		$this->filesystem = $filesystem;
	}

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$template = $this->option('template');

		if (!in_array($template, $this->availableTemplates)) {
			$this->error("Invalid template '{$template}'. Available: " . implode(', ', $this->availableTemplates));
			return Command::FAILURE;
		}

		$this->info("Creating a new wp-laracode plugin using '{$template}' template...");

		$placeholders = $this->gatherPlaceholders();
		$destinationPath = getcwd() . '/' . $placeholders['{{slug}}'];

		// 1. Copy the stub files to the new plugin directory
		$this->task('Copying base files', function () use ($destinationPath) {
			return $this->copyBaseFiles($destinationPath);
		});

		$this->renameStubFiles($destinationPath, $placeholders);

		// 2. Perform search and replace on placeholders
		$this->task('Replacing placeholders', function () use ($placeholders, $destinationPath) {
			return $this->replacePlaceholders($placeholders, $destinationPath);
		});

		// 3. Run composer install
		$this->task('Installing composer dependencies', function () use ($destinationPath) {
			return $this->runComposerInstall($destinationPath);
		});

		// 4. Create storage directories for isolation
		$this->task('Creating storage directories', function () use ($destinationPath) {
			return $this->createStorageDirectories($destinationPath);
		});

		$this->info("Plugin '{$placeholders['{{pluginName}}']}' created successfully!");
		$this->newLine();

		$this->line("🌟 <options=bold>Support the project!</> If you find wp-laracode useful, please consider giving us a star on GitHub:");
		$this->line("👉 <href=https://github.com/andexer/wp-laracode>https://github.com/andexer/wp-laracode</>");
		$this->newLine();

		$this->comment("Next steps:");
		$this->line("  1. cd {$placeholders['{{slug}}']}");
		$this->line("  2. ./{$placeholders['{{slug}}']} list");
		$this->line("  3. Copy the plugin to wp-content/plugins/ and activate it");
	}

	protected function copyBaseFiles($destinationPath): bool
	{
		$sourcePath = $this->getBaseStubPath();

		if ($this->filesystem->exists($destinationPath)) {
			if (!$this->option('force')) {
				$this->error('Directory already exists. Use --force to overwrite.');
				return false;
			}
			$this->filesystem->deleteDirectory($destinationPath);
		}

		$this->filesystem->copyDirectory($sourcePath, $destinationPath);

		return true;
	}

	protected function getBaseStubPath(): string
	{
		$template = $this->option('template') ?: 'base';

		// Check if running as a compiled phar
		if (str_starts_with(__FILE__, 'phar://')) {
			return dirname(__DIR__, 2) . '/templates/' . $template;
		}

		// Running in development
		return base_path('templates/' . $template);
	}

	protected function renameStubFiles(string $destinationPath, array $placeholders): void
	{
		// Rename the main CLI binary
		$this->filesystem->move(
			$destinationPath . '/cli.stub',
			$destinationPath . '/' . $placeholders['{{slug}}']
		);
		$this->filesystem->chmod($destinationPath . '/' . $placeholders['{{slug}}'], 0755);

		// Rename the main plugin file
		$this->filesystem->move(
			$destinationPath . '/plugin.php.stub',
			$destinationPath . '/' . $placeholders['{{slug}}'] . '.php'
		);

		// Rename all other .stub files (recursive)
		$this->renameAllStubFilesRecursively($destinationPath);
	}

	protected function renameAllStubFilesRecursively(string $path): void
	{
		$files = $this->filesystem->allFiles($path);

		foreach ($files as $file) {
			$filePath = $file->getRealPath();

			// Skip if not a .stub file
			if (!str_ends_with($filePath, '.stub')) {
				continue;
			}

			// Determine new file name (remove .stub extension)
			$newPath = substr($filePath, 0, -5); // Remove '.stub'

			// For Blade templates, keep proper extension
			if (str_ends_with($newPath, '.blade.php')) {
				// Already correct: file.blade.php.stub -> file.blade.php
			}

			$this->filesystem->move($filePath, $newPath);
		}
	}

	protected function replacePlaceholders(array $placeholders, string $destinationPath): bool
	{
		$files = $this->filesystem->allFiles($destinationPath, false);

		foreach ($files as $file) {
			$content = $this->filesystem->get($file->getRealPath());

			$newContent = str_replace(
				array_keys($placeholders),
				array_values($placeholders),
				$content
			);

			$this->filesystem->put($file->getRealPath(), $newContent);
		}

		return true;
	}

	protected function runComposerInstall(string $destinationPath): bool
	{
		$process = new Process(['composer', 'install', '--no-dev', '--quiet'], $destinationPath);
		$process->setTimeout(300);
		$process->run();

		if (!$process->isSuccessful()) {
			$this->error('Composer install failed: ' . $process->getErrorOutput());
			return false;
		}

		return true;
	}

	protected function createStorageDirectories(string $destinationPath): bool
	{
		$storagePath = $destinationPath . '/storage';

		$directories = [
			$storagePath . '/logs',
			$storagePath . '/framework/views',
			$storagePath . '/framework/cache',
			$storagePath . '/framework/sessions',
		];

		foreach ($directories as $dir) {
			if (!$this->filesystem->isDirectory($dir)) {
				$this->filesystem->makeDirectory($dir, 0755, true);
			}
		}

		// Create .gitkeep files
		foreach ($directories as $dir) {
			$gitkeep = $dir . '/.gitkeep';
			if (!$this->filesystem->exists($gitkeep)) {
				$this->filesystem->put($gitkeep, '');
			}
		}

		return true;
	}

	/**
	 * Gather all the placeholders and their values.
	 *
	 * @return array
	 */
	protected function gatherPlaceholders(): array
	{
		$name = $this->argument('name');
		$slug = Str::slug($name);
		$namespace = $this->option('namespace') ?: Str::studly($name);
		$pluginName = Str::headline($name);
		$constPrefix = Str::upper(str_replace('-', '_', Str::snake($name)));
		$functionPrefix = str_replace('-', '_', $slug);

		return [
			'{{name}}' => $name,
			'{{slug}}' => $slug,
			'{{functionPrefix}}' => $functionPrefix,
			'{{namespace}}' => $namespace,
			'{{pluginName}}' => $pluginName,
			'{{constantPrefix}}' => $constPrefix,
			'{{description}}' => $this->option('description') ?: "A new plugin named {$pluginName}.",
			'{{authorName}}' => $this->option('author') ?: 'Your Name',
			'{{authorEmail}}' => $this->option('author_email') ?: 'you@example.com',
			'{{authorUrl}}' => $this->option('author_url') ?: 'https://example.com',
			'{{vendor}}' => Str::slug($this->option('author') ?: 'your-name'),
			'{{license}}' => $this->option('license') ?: 'GPL-2.0-or-later',
		];
	}
}
