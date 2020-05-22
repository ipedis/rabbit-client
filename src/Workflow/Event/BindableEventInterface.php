<?php


namespace Ipedis\Rabbit\Workflow\Event;


interface BindableEventInterface
{
    const WORKFLOW_ON_START = 'workflow.started';
    const WORKFLOW_ON_FINISH = 'workflow.finished';
    const WORKFLOW_ON_FAILURE = 'workflow.failed';
    const WORKFLOW_ON_SUCCESS = 'workflow.successed';

    const WORKFLOW_ON_GROUPS_FAILURE = 'workflow.groups.failed';
    const WORKFLOW_ON_GROUPS_SUCCESS = 'workflow.groups.successed';
    const WORKFLOW_ON_GROUPS_FINISH = 'workflow.groups.finished';

    const WORKFLOW_ON_TASKS_FAILURE = 'workflow.tasks.failed';
    const WORKFLOW_ON_TASKS_SUCCESS = 'workflow.tasks.successed';
    const WORKFLOW_ON_TASKS_FINISH = 'workflow.tasks.finished';

    const WORKFLOW_ALLOW_TYPES = [
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

    const GROUP_ON_START = 'group.started';
    const GROUP_ON_FINISH = 'group.finished';
    const GROUP_ON_FAILURE = 'group.failed';
    const GROUP_ON_SUCCESS = 'group.successed';

    const GROUP_ON_TASKS_FAILURE = 'group.tasks.failed';
    const GROUP_ON_TASKS_SUCCESS = 'group.tasks.successed';
    const GROUP_ON_TASKS_FINISH = 'group.tasks.finished';

    const GROUP_ALLOW_TYPES = [
        self::GROUP_ON_START,
        self::GROUP_ON_FINISH,
        self::GROUP_ON_FAILURE,
        self::GROUP_ON_SUCCESS,

        self::GROUP_ON_TASKS_FAILURE,
        self::GROUP_ON_TASKS_SUCCESS,
        self::GROUP_ON_TASKS_FINISH,
    ];

    const TASK_ON_START  = 'task.started';
    const TASK_ON_PROGRESS = 'task.progressed';
    const TASK_ON_FAILURE = 'task.failed';
    const TASK_ON_SUCCESS = 'task.successed';
    const TASK_ON_FINISH = 'task.finished';
    const TASK_ON_RETRY = 'task.retried';

    const TASK_ALLOW_TYPES = [
        self::TASK_ON_START,
        self::TASK_ON_PROGRESS,
        self::TASK_ON_FAILURE,
        self::TASK_ON_SUCCESS,
        self::TASK_ON_FINISH,
        self::TASK_ON_RETRY
    ];
}
