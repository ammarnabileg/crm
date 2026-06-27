<?php

declare(strict_types=1);

namespace App\Services\Plugin;

use App\Contracts\Automation\AutomationAction;
use App\Contracts\Automation\AutomationCondition;

/**
 * The Plugin capability gateway (docs/51 Plugin SDK — Plugin API + Sandbox).
 *
 * This is the ONLY surface a plugin uses to extend the platform. A plugin's
 * register() receives a PluginApi and DECLARES capabilities (pages, menus, widgets,
 * permissions, roles, workflow nodes, interview types, evaluation templates,
 * reports, notifications, integrations, automation actions/conditions). It collects
 * declarations only — it exposes no Database, secrets, tenant data or AI keys, so a
 * plugin can never reach them directly. The PluginRegistry applies the collected
 * declarations to the host platform.
 */
final class PluginApi
{
    /** @var array<int, array<string,string>> */
    private array $permissions = [];
    /** @var array<int, array<string,mixed>> */
    private array $menuItems = [];
    /** @var array<int, array<string,mixed>> */
    private array $widgets = [];
    /** @var array<int, array<string,string>> */
    private array $roles = [];
    /** @var array<int, array<string,string>> */
    private array $workflowNodes = [];
    /** @var array<int, array<string,string>> */
    private array $interviewTypes = [];
    /** @var array<int, array<string,mixed>> */
    private array $evaluationTemplates = [];
    /** @var array<int, array<string,mixed>> */
    private array $reports = [];
    /** @var array<int, array<string,mixed>> */
    private array $notificationChannels = [];
    /** @var array<int, array<string,mixed>> */
    private array $integrations = [];
    /** @var array<int, AutomationAction> */
    private array $automationActions = [];
    /** @var array<int, AutomationCondition> */
    private array $automationConditions = [];

    public function __construct(private readonly string $pluginKey)
    {
    }

    public function pluginKey(): string
    {
        return $this->pluginKey;
    }

    public function addPermission(string $key, string $description, string $module = 'plugins'): self
    {
        $this->permissions[] = ['key' => $key, 'description' => $description, 'module' => $module];

        return $this;
    }

    /** @param array<string,mixed> $item */
    public function addMenuItem(array $item): self
    {
        $this->menuItems[] = $item;

        return $this;
    }

    /** @param array<string,mixed> $widget */
    public function addDashboardWidget(array $widget): self
    {
        $this->widgets[] = $widget;

        return $this;
    }

    public function addRole(string $key, string $name): self
    {
        $this->roles[] = ['key' => $key, 'name' => $name];

        return $this;
    }

    public function addWorkflowNode(string $key, string $label): self
    {
        $this->workflowNodes[] = ['key' => $key, 'label' => $label];

        return $this;
    }

    public function addInterviewType(string $key, string $label): self
    {
        $this->interviewTypes[] = ['key' => $key, 'label' => $label];

        return $this;
    }

    /** @param array<string,mixed> $definition */
    public function addEvaluationTemplate(string $key, array $definition): self
    {
        $this->evaluationTemplates[] = ['key' => $key, 'definition' => $definition];

        return $this;
    }

    /** @param array<string,mixed> $report */
    public function addReport(array $report): self
    {
        $this->reports[] = $report;

        return $this;
    }

    /** @param array<string,mixed> $channel */
    public function addNotificationChannel(array $channel): self
    {
        $this->notificationChannels[] = $channel;

        return $this;
    }

    /** @param array<string,mixed> $integration */
    public function addIntegration(array $integration): self
    {
        $this->integrations[] = $integration;

        return $this;
    }

    public function addAutomationAction(AutomationAction $action): self
    {
        $this->automationActions[] = $action;

        return $this;
    }

    public function addAutomationCondition(AutomationCondition $condition): self
    {
        $this->automationConditions[] = $condition;

        return $this;
    }

    // --- collected declarations (read by the PluginRegistry) ---------------

    /** @return array<int, array<string,string>> */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /** @return array<int, array<string,mixed>> */
    public function menuItems(): array
    {
        return $this->menuItems;
    }

    /** @return array<int, array<string,mixed>> */
    public function widgets(): array
    {
        return $this->widgets;
    }

    /** @return array<int, array<string,string>> */
    public function roles(): array
    {
        return $this->roles;
    }

    /** @return array<int, array<string,string>> */
    public function workflowNodes(): array
    {
        return $this->workflowNodes;
    }

    /** @return array<int, array<string,string>> */
    public function interviewTypes(): array
    {
        return $this->interviewTypes;
    }

    /** @return array<int, array<string,mixed>> */
    public function evaluationTemplates(): array
    {
        return $this->evaluationTemplates;
    }

    /** @return array<int, array<string,mixed>> */
    public function reports(): array
    {
        return $this->reports;
    }

    /** @return array<int, array<string,mixed>> */
    public function notificationChannels(): array
    {
        return $this->notificationChannels;
    }

    /** @return array<int, array<string,mixed>> */
    public function integrations(): array
    {
        return $this->integrations;
    }

    /** @return array<int, AutomationAction> */
    public function automationActions(): array
    {
        return $this->automationActions;
    }

    /** @return array<int, AutomationCondition> */
    public function automationConditions(): array
    {
        return $this->automationConditions;
    }
}
