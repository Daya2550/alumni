<?php
/**
 * Notifications Helper
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mail.php';

class Notifications {
	public static function notifyUsers(array $userIds, string $title, string $message, string $type = 'info', ?string $relatedType = null, $relatedId = null): void {
		if (empty($userIds)) { return; }
		// Insert per user in a single prepared statement loop
		foreach ($userIds as $uid) {
			if (!$uid) { continue; }
			
			// Ensure related_id is not too long (truncate to 36 chars max for UUID compatibility)
			$safeRelatedId = $relatedId ? substr((string)$relatedId, 0, 36) : null;
			
			try {
				db()->execute(
					"INSERT INTO notifications (user_id, title, message, type, related_type, related_id) VALUES (?, ?, ?, ?, ?, ?)",
					[$uid, $title, $message, $type, $relatedType, $safeRelatedId]
				);
			} catch (Exception $e) {
				error_log("Notification insert failed for user $uid: " . $e->getMessage() . " | Related ID: " . ($safeRelatedId ?? 'null') . " | Length: " . strlen($safeRelatedId ?? ''));
				// Continue with other users even if one fails
			}

			// Send email per user (respect preferences)
			self::emailUserByType((string)$uid, $title, $message, $relatedType, $relatedId);
		}
	}

	public static function notifyAllActiveUsersExcept(?string $excludeUserId, string $title, string $message, string $type = 'info', ?string $relatedType = null, $relatedId = null): void {
		// Ensure related_id is not too long (truncate to 36 chars max for UUID compatibility)
		$safeRelatedId = $relatedId ? substr((string)$relatedId, 0, 36) : null;
		
		// Efficient insert-select
		try {
			db()->execute(
				"INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
				 SELECT id, ?, ?, ?, ?, ? FROM users WHERE is_active = 1" . ($excludeUserId ? " AND id <> ?" : ""),
				$excludeUserId ? [$title, $message, $type, $relatedType, $safeRelatedId, $excludeUserId] : [$title, $message, $type, $relatedType, $safeRelatedId]
			);
		} catch (Exception $e) {
			error_log("Bulk notification insert failed: " . $e->getMessage() . " | Related ID: " . ($safeRelatedId ?? 'null') . " | Length: " . strlen($safeRelatedId ?? ''));
			// Continue with email notifications even if database insert fails
		}

		// Email broadcast to all active users (respect notification preferences)
		$rows = $excludeUserId
			? db()->fetchAll("SELECT id, email, privacy_settings FROM users WHERE is_active = 1 AND id <> ? AND email IS NOT NULL AND email != ''", [$excludeUserId])
			: db()->fetchAll("SELECT id, email, privacy_settings FROM users WHERE is_active = 1 AND email IS NOT NULL AND email != ''");
		if (!empty($rows)) {
			list($subject, $bodyHtml, $bodyText) = self::buildEmailContent($title, $message, $relatedType, $relatedId);
			foreach ($rows as $row) {
				$to = $row['email'] ?? '';
				$prefs = self::parsePrefs($row['privacy_settings'] ?? null);
				if ($to && self::shouldEmailForType($prefs, (string)$relatedType)) {
					@sendEmail($to, $subject, $bodyHtml, $bodyText);
				}
			}
		}
	}

	public static function notifyMessageParticipants(string $roomId, string $senderId, string $title, string $message): void {
		// Notify all room participants except sender
		db()->execute(
			"INSERT INTO notifications (user_id, title, message, type, related_type, related_id)
			 SELECT cp.user_id, ?, ?, 'info', 'message_room', NULL
			 FROM message_participants cp
			 WHERE cp.room_id = ? AND cp.user_id <> ?",
			[$title, $message, $roomId, $senderId]
		);

		// Email all room participants except sender (respect notify_messages)
		$emails = db()->fetchAll(
			"SELECT u.email, u.privacy_settings FROM message_participants cp JOIN users u ON u.id = cp.user_id WHERE cp.room_id = ? AND cp.user_id <> ? AND u.email IS NOT NULL AND u.email != ''",
			[$roomId, $senderId]
		);
		if (!empty($emails)) {
			list($subject, $bodyHtml, $bodyText) = self::buildEmailContent($title, $message, 'message_room', null);
			foreach ($emails as $row) {
				$to = $row['email'] ?? '';
				$prefs = self::parsePrefs($row['privacy_settings'] ?? null);
				if ($to && self::shouldEmailForType($prefs, 'message_room')) {
					@sendEmail($to, $subject, $bodyHtml, $bodyText);
				}
			}
		}
	}

	private static function emailUserByType(string $userId, string $title, string $message, ?string $relatedType, $relatedId): void {
		$u = db()->fetchOne("SELECT email FROM users WHERE id = ?", [$userId]);
		$email = $u['email'] ?? '';
		if (!$email) { return; }
		$prefs = self::parsePrefs($u['privacy_settings'] ?? null);
		if (self::shouldEmailForType($prefs, (string)$relatedType)) {
			list($subject, $bodyHtml, $bodyText) = self::buildEmailContent($title, $message, $relatedType, $relatedId);
			@sendEmail($email, $subject, $bodyHtml, $bodyText);
		}
	}

	private static function buildEmailContent(string $title, string $message, ?string $relatedType, $relatedId): array {
		$subject = $title;
		$link = '#';
		switch ($relatedType) {
			case 'news': $link = 'news_post.php?id=' . urlencode((string)$relatedId); break;
			case 'job': $link = 'job.php?id=' . urlencode((string)$relatedId); break;
			case 'event': $link = 'event.php?id=' . urlencode((string)$relatedId); break;
			case 'notice': $link = 'notice.php?id=' . urlencode((string)$relatedId); break;
			case 'message': $link = 'message.php'; break;
			case 'message_room': $link = 'message.php'; break;
			case 'survey': $link = 'survey_take.php?id=' . urlencode((string)$relatedId); break;
			case 'project': $link = 'project.php?id=' . urlencode((string)$relatedId); break;
			default: $link = 'notifications.php'; break;
		}
		$fullLink = $link;
		if (defined('APP_URL') && APP_URL) {
			$base = rtrim(APP_URL, '/');
			$path = strpos($link, '/') === 0 ? $link : ('/' . ltrim($link, '/'));
			$fullLink = $base . $path;
		}
		$bodyHtml = '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6;">'
			. '<h3 style="margin:0 0 8px 0;">' . htmlspecialchars($title) . '</h3>'
			. '<p style="margin:0 0 12px 0; color:#444;">' . nl2br(htmlspecialchars($message)) . '</p>'
			. '<p style="margin:0 0 12px 0;"><a href="' . htmlspecialchars($fullLink) . '" style="background:#0d6efd;color:#fff;padding:8px 12px;border-radius:4px;text-decoration:none;">View</a></p>'
			. '<p style="color:#888;font-size:12px;">You received this because of your activity on ' . (defined('APP_NAME') ? APP_NAME : 'the portal') . '.</p>'
			. '</div>';
		$bodyText = $title . "\n\n" . $message . "\n\n" . 'View: ' . $fullLink;
		return [$subject, $bodyHtml, $bodyText];
	}

	private static function parsePrefs($json) : array {
		if (!$json) { return []; }
		try { $arr = json_decode((string)$json, true); return is_array($arr) ? $arr : []; } catch (\Throwable $e) { return []; }
	}

	private static function shouldEmailForType(array $prefs, string $relatedType): bool {
		// Defaults: if not set, treat as enabled except for messages (enabled by default too)
		switch ($relatedType) {
			case 'message':
			case 'message_room': return !array_key_exists('notify_messages', $prefs) || !empty($prefs['notify_messages']);
			case 'job': return !array_key_exists('notify_jobs', $prefs) || !empty($prefs['notify_jobs']);
			case 'event': return !array_key_exists('notify_events', $prefs) || !empty($prefs['notify_events']);
			case 'news': return !array_key_exists('notify_news', $prefs) || !empty($prefs['notify_news']);
			default: return true; // notices, surveys, projects, feed posts
		}
	}
}

