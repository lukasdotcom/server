<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\FilesReminders\Controller;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use OCA\FilesReminders\Exception\NodeNotFoundException;
use OCA\FilesReminders\Exception\ReminderNotFoundException;
use OCA\FilesReminders\Service\ReminderService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected ReminderService $reminderService,
		protected IUserSession $userSession,
		protected LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get a reminder
	 *
	 * @param int $fileId ID of the file
	 * @return DataResponse<Http::STATUS_OK, array{dueDate: ?string}, array{}>|DataResponse<Http::STATUS_UNAUTHORIZED, list<empty>, array{}>
	 *
	 * 200: Reminder returned
	 * 401: Account not found
	 */
	#[NoAdminRequired]
	public function get(int $fileId): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$reminder = $this->reminderService->getDueForUser($user, $fileId);
			if ($reminder === null) {
				return new DataResponse(['dueDate' => null], Http::STATUS_OK);
			}
			return new DataResponse([
				'dueDate' => $reminder->getDueDate()->format(DateTimeInterface::ATOM), // ISO 8601
			], Http::STATUS_OK);
		} catch (NodeNotFoundException $e) {
			return new DataResponse(['dueDate' => null], Http::STATUS_OK);
		}
	}

	/**
	 * Set a reminder
	 *
	 * @param int $fileId ID of the file
	 * @param string $dueDate ISO 8601 formatted date time string
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_CREATED|Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Reminder updated
	 * 201: Reminder created successfully
	 * 400: Creating reminder is not possible
	 * 401: Account not found
	 * 404: File not found
	 */
	#[NoAdminRequired]
	public function set(int $fileId, string $dueDate): DataResponse {
		try {
			$dueDate = (new DateTime($dueDate))->setTimezone(new DateTimeZone('UTC'));
		} catch (Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$created = $this->reminderService->createOrUpdate($user, $fileId, $dueDate);
			if ($created) {
				return new DataResponse([], Http::STATUS_CREATED);
			}
			return new DataResponse([], Http::STATUS_OK);
		} catch (NodeNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Remove a reminder
	 *
	 * @param int $fileId ID of the file
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 200: Reminder deleted successfully
	 * 401: Account not found
	 * 404: Reminder not found
	 */
	#[NoAdminRequired]
	public function remove(int $fileId): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse([], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->reminderService->remove($user, $fileId);
			return new DataResponse([], Http::STATUS_OK);
		} catch (NodeNotFoundException|ReminderNotFoundException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}
	}
}
