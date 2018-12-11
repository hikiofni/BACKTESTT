<?php

/*
The MIT License (MIT)

Copyright (c) 2018 Jacques Archimède

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is furnished
to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

namespace App\G6K\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Renames an asset in the manifest.json file for the assets versioning.
 *
 * This command allows you to rename an asset in the manifest.json file.
 *
 * Run this command when an asset(css, js, images, ...) is renamed.
 */
class RenameAssetManifestCommand extends AssetManifestCommandBase
{

	/**
	 * @inheritdoc
	 */
	public function __construct(string $projectDir) {
		parent::__construct($projectDir);
		if (file_exists($this->projectDir . "/manifest.json")) {
			$this->manifest = json_decode(file_get_contents($this->projectDir . "/manifest.json"), true);
		} else {
			$this->manifest = array();
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function getCommandName() {
		return 'g6k:assets:manifest:rename-asset';
	}

	/**
	 * @inheritdoc
	 */
	protected function getCommandDescription() {
		return $this->translator->trans('Renames an asset in the manifest.json file for the assets versioning.');
	}

	/**
	 * @inheritdoc
	 */
	protected function getCommandHelp() {
		return
			  $this->translator->trans("This command allows you to rename an asset in the manifest.json file.")."\n"
			. "\n"
			. $this->translator->trans("You must provide:")."\n"
			. $this->translator->trans("- the path of the asset (assetpath).")."\n"
			. $this->translator->trans("- the new path of the asset (newassetpath).")."\n"
			. "\n"
			. $this->translator->trans("Run this command when an asset(css, js, images, ...) is renamed.")."\n"
		;
	}

	/**
	 * @inheritdoc
	 */
	protected function getCommandArguments() {
		return array(
			array(
				'assetpath',
				InputArgument::REQUIRED,
				$this->translator->trans('The path of the asset relatively to the public directory.')
			),
			array(
				'newassetpath',
				InputArgument::REQUIRED,
				$this->translator->trans('The new path of the asset relatively to the public directory.')
			)
		);
	}

	/**
	 * Checks the argument of the current command (g6k:assets:manifest:rename-asset).
	 *
	 * @param   \Symfony\Component\Console\Input\InputInterface $input The input interface
	 * @param   \Symfony\Component\Console\Output\OutputInterface $output The output interface
	 * @return  void
	 *
	 */
	protected function interact(InputInterface $input, OutputInterface $output) {
		$file = $input->getArgument('assetpath');
		if (! $file) {
			$questionHelper = $this->getHelper('question');
			$question = new Question($this->translator->trans("Enter the path of the asset relatively to the public directory : "));
			$file = $questionHelper->ask($input, $output, $question);
			if ($file !== null) {
				$input->setArgument('assetpath', $file);
			}
			$output->writeln('');
		}
		$file = $input->getArgument('newassetpath');
		if (! $file) {
			$questionHelper = $this->getHelper('question');
			$question = new Question($this->translator->trans("Enter the new path of the asset relatively to the public directory : "));
			$file = $questionHelper->ask($input, $output, $question);
			if ($file !== null) {
				$input->setArgument('newassetpath', $file);
			}
			$output->writeln('');
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$file = $input->getArgument('assetpath');
		$newfile = $input->getArgument('newassetpath');
		$output->writeln([
			$this->translator->trans("G6K version %s%", array('%s%' => $this->version)),
			'',
			$this->translator->trans("Asset manifest editor"),
			'===============================================',
			'',
		]);
		$this->publicDir = $this->projectDir . DIRECTORY_SEPARATOR . $this->parameters['public_dir'];
		if (file_exists($this->publicDir . "/" . $file)) {
			$output->writeln("<error>" .  $this->translator->trans("Asset manifest: The file '%s%' still exists.", array('%s%' => $file)) . "</error>");
			return 1;
		}
		if (! file_exists($this->publicDir . "/" . $newfile)) {
			$output->writeln("<error>" . $this->translator->trans("Asset manifest: The file '%s%' doesn't exists", array('%s%' => $newfile)) . "</error>");
			return 1;
		}
		$file = str_replace('\\', '/', $file);
		if (!isset($this->manifest[$file])) {
			$output->writeln("<error>" .  $this->translator->trans("Asset manifest: The file '%s%' isn't in the manifest.", array('%s%' => $file)) . "</error>");
			return 1;
		}
		$newfile = str_replace('\\', '/', $newfile);
		if (isset($this->manifest[$newfile])) {
			$output->writeln("<error>" .  $this->translator->trans("Asset manifest: The file '%s%' is already in the manifest.", array('%s%' => $newfile)) . "</error>");
			return 1;
		}
		try {
			$this->manifest[$newfile] = $this->manifest[$file];
			$removed = $this->processFile($file, $output);
			if ($removed) {
				$manifest = json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
				$fsystem = new Filesystem();
				$fsystem->dumpFile($this->projectDir . "/manifest.json", $manifest);
			}
			$output->writeln($this->translator->trans("Asset manifest: The asset is successfully renamed"));
			return 0;
		} catch (IOExceptionInterface $e) {
			$output->writeln("<error>" .  $this->translator->trans("Asset manifest: Fail to update the asset manifest : %s%", array('%s%' => $e->getMessage())) . "</error>");
			return 1;
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function addFile(string $file, OutputInterface $output) {
		return false;
	}

	/**
	 * @inheritdoc
	 */
	protected function processFile(string $file, OutputInterface $output) {
		if (isset($this->manifest[$file])) {
			unset($this->manifest[$file]);
			return true;
		}
		return false;
	}

}