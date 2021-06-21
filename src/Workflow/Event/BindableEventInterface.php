<?php

namespace Ipedis\Rabbit\Workflow\Event;

interface BindableEventInterface
{
    public const WORKFLOW_ON_START = 'workflow.started';
    public const WORKFLOW_ON_FINISH = 'workflow.finished';
    public const WORKFLOW_ON_FAILURE = 'workflow.failed';
    public const WORKFLOW_ON_SUCCESS = 'workflow.successed';

    public const WORKFLOW_ON_GROUPS_FAILURE = 'workflow.groups.failed';
    public const WORKFLOW_ON_GROUPS_SUCCESS = 'workflow.groups.successed';
    public const WORKFLOW_ON_GROUPS_FINISH = 'workflow.groups.finished';

    public const WORKFLOW_ON_TASKS_FAILURE = 'workflow.tasks.failed';
    public const WORKFLOW_ON_TASKS_SUCCESS = 'workflow.tasks.successed';
    public const WORKFLOW_ON_TASKS_FINISH = 'workflow.tasks.finished';

    public const WORKFLOW_ALLOW_TYPES = [
        self::WORKFLOW_ON_START,
        self::WORKFLOW_ON_FINISH,
        self::WORKFLOW_ON_FAILURE,
        self::WORKFLOW_ON_SUCCESS,

        self::WORKFLOW_ON_GROUPS_FAILURE,
        self::WORKFLOW_ON_GROUPS_SUCCESS,
        self::WORKFLOW_ON_GROUPS_FINISH,

        self::WORKFLOW_ON_TASKS_FAILURE,
        self::WORKFLOW_ON_TASKS_SUCCESS,
        self::WORKFLOW_ON_TASKS_FINISH,
    ];

    public const GROUP_ON_START = 'group.started';
    public const GROUP_ON_FINISH = 'group.finished';
    public const GROUP_ON_FAILURE = 'group.failed';
    public const GROUP_ON_SUCCESS = 'group.successed';

    public const GROUP_ON_TASKS_FAILURE = 'group.tasks.failed';
    public const GROUP_ON_TASKS_SUCCESS = 'group.tasks.successed';
    public const GROUP_ON_TASKS_FINISH = 'group.tasks.finished';

    public const GROUP_ALLOW_TYPES = [
        self::GROUP_ON_START,
        self::GROUP_ON_FINISH,
        self::GROUP_ON_FAILURE,
        self::GROUP_ON_SUCCESS,

        self::GROUP_ON_TASKS_FAILURE,
        self::GROUP_ON_TASKS_SUCCESS,
        self::GROUP_ON_TASKS_FINISH,
    ];

    public const TASK_ON_START  = 'task.started';
    public const TASK_ON_PROGRESS = 'task.progressed';
    public const TASK_ON_FAILURE = 'task.failed';
    public const TASK_ON_SUCCESS = 'task.successed';
    public const TASK_ON_FINISH = 'task.finished';
    public const TASK_ON_RETRY = 'task.retried';

    public const TASK_ALLOW_TYPES = [
        self::TASK_ON_START,
        self::TASK_ON_PROGRESS,
        self::TASK_ON_FAILURE,
        self::TASK_ON_SUCCESS,
        self::TASK_ON_FINISH,
        self::TASK_ON_RETRY
    ];
}
