<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\SystemTags\Listeners;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\SystemTags\AppInfo\Application;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\Util;

/**
 * @template-implements IEventListener<LoadAdditionalScriptsEvent>
 */
class LoadAdditionalScriptsListener implements IEventListener {
	public function __construct(
		private IAppConfig $appConfig,
		private IInitialState $initialState,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}
		Util::addInitScript(Application::APP_ID, 'init');

		$restrictSystemTagsCreationToAdmin = $this->appConfig->getValueBool(Application::APP_ID, 'restrict_creation_to_admin', false);
		$this->initialState->provideInitialState('restrictSystemTagsCreationToAdmin', $restrictSystemTagsCreationToAdmin);
	}
}
