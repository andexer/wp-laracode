<?php

namespace App\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class SetupCommand extends Command
{
	/**
	 * The signature of the command.
	 *
	 * @var string
	 */
	protected $signature = 'setup';

	/**
	 * The description of the command.
	 *
	 * @var string
	 */
	protected $description = 'Interactively initialize the plugin configuration.';

	/** @var Filesystem */
	protected $filesystem;

	/**
	 * Helper to keep track of the current binary name.
	 */
	protected $currentBinary = 'wp-laracode';

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
		// Prevent running setup if already setup (check for templates)
		if (!$this->filesystem->exists(base_path('templates'))) {
			$this->error('Templates directory not found. This project might already be initialized.');
			return Command::FAILURE;
		}

		$this->info("Welcome to wp-laracode! Let's configure your new WordPress plugin.");
		$this->newLine();

		// 1. Gather Input
		$this->line("Please answer the following questions to configure your plugin:");
		$this->newLine();

		$defaultName = basename(getcwd());
		$pluginName = $this->ask('Plugin Name', Str::headline($defaultName));

		$pluginDescription = $this->ask('Description', 'A generated WordPress plugin.');

		$user = get_current_user();
		$authorName = $this->ask('Author Name', $user ?: 'John Doe');
		$authorEmail = $this->ask('Author Email', 'user@project.dev');

		$defaultVendor = Str::slug($authorName);
		if ($defaultVendor === 'user' || empty($defaultVendor)) {
			$defaultVendor = 'vendor';
		}
		$vendor = $this->ask('Vendor (for composer namespace)', $defaultVendor);

		$template = $this->choice('Which template would you like to use?', ['base'], 'base');

		$slug = Str::slug($pluginName);
		$defaultNamespace = Str::studly($pluginName);
		$namespace = $this->ask('Namespace (PHP)', $defaultNamespace);

		$placeholders = [
			'{{name}}' => $pluginName, // Provide raw name if needed
			'{{slug}}' => $slug,
			'{{functionPrefix}}' => str_replace('-', '_', $slug),
			'{{namespace}}' => $namespace,
			'{{pluginName}}' => $pluginName,
			'{{constantPrefix}}' => Str::upper(str_replace('-', '_', Str::snake($pluginName))),
			'{{description}}' => $pluginDescription,
			'{{authorName}}' => $authorName,
			'{{authorEmail}}' => $authorEmail,
			'{{authorUrl}}' => '', // Optional
			'{{vendor}}' => $vendor,
			'{{license}}' => 'MIT', // Defaulting for simplicity in wizard, could ask
			'{{version}}' => '1.0.0', // Standard initial version
		];

		$this->comment("Setting up plugin '{$pluginName}'...");

		// 2. Prepare Source and Destination
		$sourcePath = base_path("templates/{$template}");
		$destinationPath = base_path();

		// 3. Copy templates to root (Overwrite)
		// We iterate files in template and copy them to root
		$this->copyTemplates($sourcePath, $destinationPath);

		// 4. Rename specific files from templates that are now in root
		// The templates folder has 'cli.stub', 'plugin.php.stub' etc. 
		// We copied them as '.stub' to root. We need to rename them.

		// Rename cli.stub -> slug (The Binary)
		if ($this->filesystem->exists($destinationPath . '/cli.stub')) {
			$this->filesystem->move($destinationPath . '/cli.stub', $destinationPath . '/' . $slug);
			$this->filesystem->chmod($destinationPath . '/' . $slug, 0755);
		}

		// Rename plugin.php.stub -> slug.php
		if ($this->filesystem->exists($destinationPath . '/plugin.php.stub')) {
			$this->filesystem->move($destinationPath . '/plugin.php.stub', $destinationPath . '/' . $slug . '.php');
		}

		// Rename composer.json.stub -> composer.json
		if ($this->filesystem->exists($destinationPath . '/composer.json.stub')) {
			// This overwrites the current composer.json!
			$this->filesystem->move($destinationPath . '/composer.json.stub', $destinationPath . '/composer.json');
		}

		// Rename remaining .stub files recursively in root
		$this->renameTemplates($destinationPath);

		// 5. Replace placeholders in all files in root options
		$this->replacePlaceholders($placeholders, $destinationPath);

		// 6. Create storage directories for isolation
		$this->createStorageDirectories($destinationPath);

		// 7. Delete Templates Directory (Cleanup)
		$this->filesystem->deleteDirectory(base_path('templates'));

		// 7. Remove the original binary if it's different from the new one
		if ($slug !== $this->currentBinary && $this->filesystem->exists(base_path($this->currentBinary))) {
			$this->filesystem->delete(base_path($this->currentBinary));
		}

		// 8. Pre-Composer Cleanup & Fixes
		// Remove generator commands to avoid PSR-4 violation warnings during auto-discovery
		$commandsPath = base_path('app/Commands');
		$filesToDelete = [
			$commandsPath . '/NewCommand.php',
			$commandsPath . '/InspireCommand.php',
		];
		foreach ($filesToDelete as $file) {
			if ($this->filesystem->exists($file)) {
				$this->filesystem->delete($file);
			}
		}

		// Dynamically fix SetupCommand's own namespace to match the new project structure
		// This trick allows Composer to verify the class location correctly during the update
		$setupCommandPath = $commandsPath . '/SetupCommand.php';
		if ($this->filesystem->exists($setupCommandPath)) {
			$content = $this->filesystem->get($setupCommandPath);
			$newNamespace = $namespace . '\\Commands';
			$content = str_replace('namespace App\\Commands;', "namespace {$newNamespace};", $content);

			// Also ensure we don't break the class loading in memory (it's already loaded),
			// but the file on disk must match for Composer.
			$this->filesystem->put($setupCommandPath, $content);
		}

		// 9. Run Composer Update
		$this->info('Running composer update to finalize configuration...');
		$this->runComposerUpdate();

		// 10. Final Cleanup (Self-Destruct)
		// Now we can delete SetupCommand
		if ($this->filesystem->exists($setupCommandPath)) {
			$this->filesystem->delete($setupCommandPath);
		}

		$this->info("Configuration complete! Your plugin '{$pluginName}' is ready.");
		$this->comment("Binary: ./{$slug}");
		$this->comment("Entry: {$slug}.php");
	}

	// Removed cleanupGeneratorCommands method as it's now inline and split

	protected function copyTemplates($source, $destination)
	{
		$files = $this->filesystem->allFiles($source);
		foreach ($files as $file) {
			$relativePath = $file->getRelativePathname();
			$destPath = $destination . '/' . $relativePath;

			// Ensure dir exists
			$this->filesystem->ensureDirectoryExists(dirname($destPath));

			// Copy
			$this->filesystem->copy($file->getRealPath(), $destPath);
		}
	}

	protected function renameTemplates($path)
	{
		$files = $this->filesystem->allFiles($path);
		foreach ($files as $file) {
			if (str_contains($file->getRealPath(), '/vendor/')) continue;

			if (str_ends_with($file->getFilename(), '.stub')) {
				$newPath = substr($file->getRealPath(), 0, -5);
				// Handle blade special case if needed, but usually blade.php.stub -> blade.php is fine
				$this->filesystem->move($file->getRealPath(), $newPath);
			}
		}
	}

	protected function replacePlaceholders($placeholders, $path)
	{
		$files = $this->filesystem->allFiles($path);
		// Filter out vendor to enable faster processing
		$files = array_filter($files, fn($f) => !str_contains($f->getRealPath(), '/vendor/'));

		foreach ($files as $file) {
			// Skip binary files if any? (images etc)
			// Simple safeguard:
			$content = $this->filesystem->get($file->getRealPath());

			// Skip binary files (simple check for null bytes)
			if (str_contains($content, "\0")) {
				continue;
			}
			$newContent = str_replace(array_keys($placeholders), array_values($placeholders), $content);
			$this->filesystem->put($file->getRealPath(), $newContent);
		}
	}

	protected function runComposerUpdate()
	{
		$process = new Process(['composer', 'update'], base_path());
		$process->setTimeout(600);
		$process->run(function ($type, $buffer) {
			$this->output->write($buffer);
		});

		$this->info('Optimizing class loader...');
		$process = new Process(['composer', 'dump-autoload', '-o'], base_path());
		$process->setTimeout(600);
		$process->run();
	}

	protected function createStorageDirectories(string $destinationPath): void
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

			// Create .gitkeep
			$gitkeep = $dir . '/.gitkeep';
			if (!$this->filesystem->exists($gitkeep)) {
				$this->filesystem->put($gitkeep, '');
			}
		}
	}
}
