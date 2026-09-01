<?php
namespace Opencart\Admin\Controller\Tool;
/**
 * Class RuntimeDiagnostics
 *
 * @package Opencart\Admin\Controller\Tool
 */
class RuntimeDiagnostics extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('tool/runtime_diagnostics');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('tool/runtime_diagnostics', 'user_token=' . $this->session->data['user_token'])
		];

		$data['release_check'] = $this->url->link('tool/runtime_diagnostics.release', 'user_token=' . $this->session->data['user_token']);
		$data['can_modify'] = $this->user->hasPermission('modify', 'tool/runtime_diagnostics');

		$this->load->model('tool/runtime_diagnostics');
		$data['diagnostics'] = $this->model_tool_runtime_diagnostics->getDiagnostics();

		$this->load->model('setting/event');

		$data['events'] = [];

		foreach ($this->model_setting_event->getEvents() as $event) {
			$data['events'][] = [
				'trigger_resolution' => $this->resolveTrigger($event['trigger']),
				'action_resolution'  => $this->resolveAction($event['trigger'], $event['action'])
			] + $event;
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('tool/runtime_diagnostics', $data));
	}

	/**
	 * Check GitHub for a newer stable OpenCore release.
	 *
	 * @return void
	 */
	public function release(): void {
		$this->load->language('tool/runtime_diagnostics');

		$json = [];

		if (!$this->user->hasPermission('modify', 'tool/runtime_diagnostics')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			try {
				$this->load->model('tool/release');

				$result = $this->model_tool_release->check($this->language->get('text_release_available_title'), $this->language->get('text_release_available'));

				$json['success'] = match ($result['status']) {
					'notified' => sprintf($this->language->get('text_release_notified'), $result['version']),
					'already_notified' => sprintf($this->language->get('text_release_already_notified'), $result['version']),
					default => $this->language->get('text_release_current')
				};

				if (isset($result['version']) && is_string($result['version'])) {
					$json['latest_stable_version'] = $result['version'];
				}
			} catch (\Throwable $e) {
				$json['error'] = $this->language->get('error_release_check');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Resolve Event Trigger
	 *
	 * @param string $trigger
	 *
	 * @return string
	 */
	private function resolveTrigger(string $trigger): string {
		if (str_starts_with($trigger, 'system/')) {
			return 'unsupported';
		}

		if (!preg_match('/^(admin|catalog)\/(model|controller)\/([a-z0-9_]+(?:\/[a-z0-9_]+)*)\.([a-zA-Z0-9_]+)\/(before|after)$/', $trigger, $match)) {
			return 'invalid';
		}

		$directory = $match[1] == 'admin' ? DIR_APPLICATION : DIR_CATALOG;
		$file = $directory . $match[2] . '/' . $match[3] . '.php';

		return is_file($file) ? 'ok' : 'missing';
	}

	/**
	 * Resolve Event Action
	 *
	 * @param string $trigger
	 * @param string $action
	 *
	 * @return string
	 */
	private function resolveAction(string $trigger, string $action): string {
		if (str_starts_with($trigger, 'admin/')) {
			$directory = DIR_APPLICATION;
		} elseif (str_starts_with($trigger, 'catalog/')) {
			$directory = DIR_CATALOG;
		} else {
			return 'invalid';
		}

		$route = $this->parseControllerRoute($action);

		if ($route === '') {
			return 'invalid';
		}

		return is_file($directory . 'controller/' . $route . '.php') ? 'ok' : 'missing';
	}

	/**
	 * Parse Controller Route
	 *
	 * @param string $action
	 *
	 * @return string
	 */
	private function parseControllerRoute(string $action): string {
		if (!preg_match('/^([a-z0-9_]+(?:\/[a-z0-9_]+)*)(?:\.[a-zA-Z0-9_]+)?$/', $action, $match)) {
			return '';
		}

		return $match[1];
	}
}
