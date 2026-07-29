/**
 * @file
 * Drag and drop of task cards between the columns of the Kanban board.
 *
 * Uses the native HTML5 Drag and Drop API only. A drop moves the card in the
 * DOM straight away and persists the new status through a POST request, so the
 * page is never reloaded.
 */

((Drupal, once) => {
  /**
   * The CSRF token, fetched once per page and reused for every drop.
   */
  let csrfToken = null;

  /**
   * Returns the CSRF token required by the status update route.
   *
   * @return {Promise<string>}
   *   The token issued by the core /session/token route.
   */
  const getToken = async () => {
    if (csrfToken === null) {
      const response = await fetch(Drupal.url('session/token'));
      csrfToken = await response.text();
    }
    return csrfToken;
  };

  /**
   * Saves the status a card was dropped into.
   *
   * @param {string} nid
   *   The node id of the task.
   * @param {string} status
   *   The machine name of the target status.
   *
   * @return {Promise<void>}
   *   Resolves once the node has been saved.
   */
  const saveStatus = async (nid, status) => {
    const response = await fetch(Drupal.url(`task-board/task/${nid}/status`), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': await getToken(),
      },
      body: JSON.stringify({ status }),
    });
    if (!response.ok) {
      throw new Error(`Status update failed with ${response.status}.`);
    }
  };

  /**
   * Rewrites the card counter of every column of a board.
   *
   * @param {Element} board
   *   The board wrapper the columns belong to.
   */
  const refreshCounts = (board) => {
    board.querySelectorAll('.task-board__column').forEach((column) => {
      const counter = column.querySelector('.task-board__count');
      if (counter) {
        counter.textContent = column.querySelectorAll('.task-card').length;
      }
    });
  };

  Drupal.behaviors.taskBoard = {
    attach(context) {
      once('task-board-card', '.task-card', context).forEach((card) => {
        card.addEventListener('dragstart', (event) => {
          event.dataTransfer.setData('text/plain', card.dataset.nid);
          event.dataTransfer.effectAllowed = 'move';
          card.classList.add('is-dragging');
        });

        card.addEventListener('dragend', () => {
          card.classList.remove('is-dragging');
        });
      });

      once('task-board-column', '.task-board__column', context).forEach(
        (column) => {
          column.addEventListener('dragover', (event) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            column.classList.add('is-drop-target');
          });

          column.addEventListener('dragleave', () => {
            column.classList.remove('is-drop-target');
          });

          column.addEventListener('drop', async (event) => {
            event.preventDefault();
            column.classList.remove('is-drop-target');

            const nid = event.dataTransfer.getData('text/plain');
            const board = column.closest('.task-board');
            const card = board
              ? board.querySelector(`.task-card[data-nid="${nid}"]`)
              : null;
            if (!card) {
              return;
            }

            const origin = card.parentNode;
            const target = column.querySelector('.task-board__cards');
            if (target === origin) {
              return;
            }

            target.appendChild(card);
            refreshCounts(board);

            try {
              await saveStatus(nid, column.dataset.status);
              Drupal.announce(Drupal.t('Task status updated.'));
            } catch (error) {
              origin.appendChild(card);
              refreshCounts(board);
              Drupal.announce(
                Drupal.t('The task status could not be saved.'),
                'assertive',
              );
            }
          });
        },
      );
    },
  };
})(Drupal, once);
