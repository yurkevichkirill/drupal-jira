# Ревью части 1 — Block Plugin «Project statistics»

## Context

Задание требует три core-плагина (Block, Field Widget, Field Formatter) поверх уже готового
`TaskStatService` (`web/modules/custom/task_stat/src/Service/TaskStatService.php`, сервис
`drupaljira.task_stat`). Сделана попытка первого — блока статистики проекта. Текущий файл
`web/modules/custom/project_statistics/src/Plugin/ProjectStatistics.php` **не парсится PHP**
(`php -l` → `syntax error, unexpected token ";" on line 47`), модуля как такового нет, и даже
после починки синтаксиса блок не заработал бы. Ниже — все найденные проблемы и готовый
исправленный вариант.

---

## Найденные ошибки

### Блокирующие (без них не работает вообще)

**1. Фатальный синтаксис в `build()`, строки 47–50**

```php
return [
  '#markup' => $this->t('This plugin is available only for project and task nodes');  // ← `;` внутри массива
]                                                                                      // ← нет `;`
```

**2. Нет `namespace`**

Файл начинается с `declare(strict_types=1);` и сразу `use ...`. Без
`namespace Drupal\project_statistics\Plugin\Block;` PSR-4 автозагрузчик класс не найдёт.

**3. Неверный путь файла**

`BlockManager` сканирует подкаталог `Plugin/Block` каждого модуля. Файл лежит в `src/Plugin/`.
Нужно `src/Plugin/Block/ProjectStatistics.php`.

**4. Нет `project_statistics.info.yml`**

Каталога модуля для Drupal не существует — его нельзя включить, плагин не будет обнаружен.

**5. Инвертированное условие, строка 54**

```php
if (!$project) {
  $result = $this->taskStatService->getProjectStats($project);  // ← вызов с NULL
} else {
  return ['#markup' => $this->t('Node not found')];             // ← сюда попадает валидный проект
}
```

`getProjectStats(NodeInterface $project)` типизирован — на странице без проекта будет
`TypeError` и WSOD, а на странице проекта — «Node not found». Ровно то, что запрещает
критерий приёмки «не приводит к PHP/500 ошибкам».

**6. `getEntity()` вместо `->entity`, строка 74**

```php
$project = $node->get('field_project')->getEntity();
```

`FieldItemList::getEntity()` возвращает **host-сущность** — то есть саму задачу, а не проект,
на который она ссылается. Проверка `instanceof NodeInterface` пройдёт (задача — тоже нода),
и блок молча покажет статистику «проекта», у которого 0 задач. Правильно —
`$node->get('field_project')->entity` (или `->referencedEntities()[0] ?? NULL`).

**7. Контекст объявлен, но не может быть заполнен**

```php
context_definitions: ['node' => new ContextDefinition('entity:node', ..., required: FALSE)]
```

Механика ядра (`web/core/modules/block/src/BlockAccessControlHandler.php:122` и
`BlockViewBuilder.php:142`):

```php
$contexts = $this->contextRepository->getRuntimeContexts(array_values($block_plugin->getContextMapping()));
$this->contextHandler->applyContextMapping($block_plugin, $contexts);
```

Подтягиваются только те контексты, что перечислены в `context_mapping` конфигурации блока.
А `context_mapping` записывается в `BlockForm::submitForm()` (строка 340) из значения
подформы плагина — которое туда попадает **только если плагин сам добавил элемент**
`addContextAssignmentElement()` в `blockForm()`. `BlockBase::buildConfigurationForm()`
этого не делает автоматически (проверено в `BlockPluginTrait::buildConfigurationForm()`).

Итог: `context_mapping` останется пустым, `applyContextMapping()` для необязательного
контекста просто снимет его без исключения, `getContextValue('node')` вернёт `NULL` — блок
**всегда** покажет заглушку, даже на странице проекта.

**8. `use DrupalPractice\Project;`**

Случайный импорт класса сниффера PHPCS (автоимпорт IDE). Удалить.

### Функциональные

**9. `item_list` с ассоциативным массивом**

```php
'#items' => $result,  // ['total_tasks' => 5, 'done_tasks' => 2, ...]
```

`item_list` игнорирует ключи и выведет голый список `5 / 2 / 12.5 / …` без подписей.
Критерий «блок показывает значения `getProjectStats()`» формально выполнен, но читать это
нельзя. Нужны подписанные строки (или `#type => 'table'`).

**10. Нет cache metadata**

Блок кэшируется render-кэшем. Без `getCacheContexts()` с `route` один и тот же результат
покажется на всех страницах; без cache tags статистика не обновится при изменении задач и
списаний времени. Нужны:
- контекст `route`;
- теги `node_list:task` (появление/изменение задач), `time_log_list` (списания времени
  — сущность `time_log` из `time_tracking`), плюс теги самого проекта.

### Стиль (PHPCS Drupal + DrupalPractice — упадут в GrumPHP на коммите)

- Открывающая `{` класса и методов должна быть на той же строке (`class Foo {`, `public function build() {`).
- Нет docblock у файла, класса и всех методов; нет `{@inheritdoc}` у `create()`/`build()`.
- Нет `admin_label` в `#[Block]` → в `/admin/structure/block` блок будет без внятного имени.
  Критерий приёмки требует имя «Project statistics». Заодно стоит задать `category`.
- Нет return type у `create()` (`: static`) и `build()` (`: array`).
- Двойной пробел: `$project =  $node->get(...)`.
- Отсутствуют завершающие запятые в многострочных массивах/аргументах.

---

## Рекомендуемое исправление

Определять проект **по маршруту через `current_route_match`**, а не через plugin context.
Техническое ограничение задания сформулировано именно так («через сервисы routing/current
request Drupal, а не ручной парсинг URL»), при этом отпадает вся возня с `context_mapping`
и блок работает сразу после размещения.

> Альтернатива, если хочется остаться на контекстах: оставить `context_definitions`, но
> добавить в `blockForm()` вызов `$this->addContextAssignmentElement($this, $this->contextRepository->getAvailableContexts())`
> (трейт уже подключён в `BlockBase`, `@context.repository` инжектить в конструктор) и при
> размещении выбрать «Node from URL». Работает, но требует лишнего шага в UI и попадает в
> экспорт конфигурации.

### Файл 1 — `web/modules/custom/project_statistics/project_statistics.info.yml`

```yaml
name: 'Project statistics'
type: module
description: 'Project statistics block, hours/minutes widget and time summary formatter for DrupalJira.'
package: DrupalJira
core_version_requirement: ^11
dependencies:
  - drupal:node
  - drupal:block
  - task_stat:task_stat
```

### Файл 2 — `web/modules/custom/project_statistics/src/Plugin/Block/ProjectStatistics.php`

(старый `src/Plugin/ProjectStatistics.php` удалить)

```php
<?php

/**
 * @file
 * Contains the project statistics block plugin.
 */

declare(strict_types=1);

namespace Drupal\project_statistics\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\task_stat\Service\TaskStatService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows the aggregated time figures of the project of the current page.
 */
#[Block(
  id: 'project_statistics',
  admin_label: new TranslatableMarkup('Project statistics'),
  category: new TranslatableMarkup('DrupalJira'),
)]
final class ProjectStatistics extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a ProjectStatistics block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\task_stat\Service\TaskStatService $taskStatService
   *   The task statistics service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly TaskStatService $taskStatService,
    protected readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('drupaljira.task_stat'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $project = $this->getProjectFromRoute();

    if (!$project instanceof NodeInterface) {
      return [
        '#markup' => $this->t('Statistics are only available on project and task pages.'),
      ];
    }

    $stats = $this->taskStatService->getProjectStats($project);

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Statistics of "@project"', ['@project' => $project->label()]),
      '#items' => [
        $this->t('Tasks: @value', ['@value' => $stats['total_tasks']]),
        $this->t('Done: @value', ['@value' => $stats['done_tasks']]),
        $this->t('Total estimate: @value h', ['@value' => number_format((float) $stats['total_estimate'], 2)]),
        $this->t('Total logged: @value h', ['@value' => number_format((float) $stats['total_logged'], 2)]),
        $this->t('Over estimate: @value', ['@value' => $stats['over_estimate_tasks']]),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // The rendered figures depend on the node of the current route.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    // Any task or time log change alters the aggregated numbers.
    $tags = ['node_list:task', 'time_log_list'];

    $project = $this->getProjectFromRoute();
    if ($project instanceof NodeInterface) {
      $tags = Cache::mergeTags($tags, $project->getCacheTags());
    }

    return Cache::mergeTags(parent::getCacheTags(), $tags);
  }

  /**
   * Resolves the project the current page belongs to.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The project node itself on a project page, the referenced project on a
   *   task page, NULL on any other route.
   */
  private function getProjectFromRoute(): ?NodeInterface {
    $node = $this->routeMatch->getParameter('node');

    if (!$node instanceof NodeInterface) {
      return NULL;
    }

    if ($node->bundle() === 'project') {
      return $node;
    }

    if ($node->bundle() === 'task' && $node->hasField('field_project')) {
      $project = $node->get('field_project')->entity;

      return $project instanceof NodeInterface ? $project : NULL;
    }

    return NULL;
  }

}
```

Если по критерию «корректно скрывается» предпочтительнее не показывать блок совсем, вместо
заглушки в `build()` добавить:

```php
protected function blockAccess(AccountInterface $account): AccessResultInterface {
  return AccessResult::allowedIf($this->getProjectFromRoute() instanceof NodeInterface)
    ->addCacheContexts(['route']);
}
```

---

## Что дальше (части 2 и 3 — ещё не начаты)

Поле уже подтверждено как `decimal` (`config/sync/field.storage.node.field_estimate.yml`:
`type: decimal`, `precision: 10`, `scale: 2`), сейчас на нём стоят core-плагины
`type: number` (form display) и `type: number_decimal` (view display, в `default` и `full`).
Значит:

- Widget: `src/Plugin/Field/FieldWidget/HoursMinutesWidget.php`, `#[FieldWidget(id: ..., field_types: ['decimal'])]`,
  `formElement()` даёт два `#type => 'number'`, обратная сборка — через `massageFormValues()`
  (`$hours + $minutes / 60`), предзаполнение — `intdiv()` / `round(fmod($value, 1) * 60)`.
- Formatter: `src/Plugin/Field/FieldFormatter/TimeSummaryFormatter.php`,
  `#[FieldFormatter(id: ..., field_types: ['decimal'])]`, внутри `viewElements()` —
  `$items->getEntity()` → `TaskStatService::getRemainingEstimate()`; отрицательный остаток
  выводить отдельной формулировкой («превышение на X ч»), а не как положительное число.

---

## Проверка

```bash
ddev drush en project_statistics -y
ddev drush cr
ddev exec vendor/bin/phpcs                  # ожидается 0 ошибок
ddev exec vendor/bin/phpstan analyse        # ожидается 0 ошибок
```

Ручные проверки:
1. `/admin/structure/block` — блок «Project statistics» есть в списке в категории DrupalJira.
2. Разместить в регионе Content темы `stark`, открыть ноду проекта — цифры совпадают с
   `getProjectStats()` (сверить с `/admin/drupaljira/timelog-debug/sum/{task}` по задачам).
3. Открыть ноду задачи этого проекта — те же цифры.
4. Открыть `/user/login` и главную — заглушка, без 500; `/admin/reports/dblog` чист.
5. Списать время через `/task/{task}/log-time` и обновить страницу проекта — `total_logged`
   изменился (проверка cache tags).
6. `ddev drush cex -y` → в диффе появляется `block.block.*` для нового блока; закоммитить
   вместе с кодом модуля.
