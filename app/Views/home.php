<main class="home">
    <section class="home-operations">
        <?php if (empty($last_operation) && empty($next_operation)) { ?>
        <p class="empty-state">Aucune opération pour le moment.</p>
        <?php } else { ?>
            <?php if (!empty($last_operation)) { ?>
            <a class="operation-card" href="/operations/<?php echo $last_operation->id ?>">
                <h2>Dernière opération : <?php echo esc($last_operation->name) ?></h2>
                <p><?php echo (new DateTime($last_operation->date))->format('d/m/Y') ?></p>
                <p><?php echo esc($last_operation->location) ?></p>
                <?php if (!empty($last_operation->scenarist_name)) { ?>
                <p class="operation-scenarist">Par <?php echo esc($last_operation->scenarist_name) ?></p>
                <?php } ?>
                <?php if (isset($visible_averages[$last_operation->id])) { $avg = $visible_averages[$last_operation->id]; ?>
                <div class="card-ratings">
                    <?php foreach ($vote_criteria_short as $key => $label) { $avg_field = $key . '_avg'; ?>
                    <div class="card-rating-row">
                        <span><?php echo esc($label) ?></span>
                        <span class="card-rating-value">&#9733; <?php echo $avg->$avg_field ?></span>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </a>
            <?php } ?>
            <?php if (!empty($next_operation)) { ?>
            <a class="operation-card" href="/operations/<?php echo $next_operation->id ?>">
                <h2><?php echo $next_operation_today ? "Aujourd'hui" : 'Prochaine opération' ?> : <?php echo esc($next_operation->name) ?></h2>
                <p><?php echo $next_operation_today ? "Aujourd'hui" : (new DateTime($next_operation->date))->format('d/m/Y') ?></p>
                <p><?php echo esc($next_operation->location) ?></p>
                <?php if (!empty($next_operation->scenarist_name)) { ?>
                <p class="operation-scenarist">Par <?php echo esc($next_operation->scenarist_name) ?></p>
                <?php } ?>
                <?php if (isset($visible_averages[$next_operation->id])) { $avg = $visible_averages[$next_operation->id]; ?>
                <div class="card-ratings">
                    <?php foreach ($vote_criteria_short as $key => $label) { $avg_field = $key . '_avg'; ?>
                    <div class="card-rating-row">
                        <span><?php echo esc($label) ?></span>
                        <span class="card-rating-value">&#9733; <?php echo $avg->$avg_field ?></span>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </a>
            <?php } ?>
        <?php } ?>
    </section>

    <section class="barracks-container">
      <div class="content">
          <?php foreach ($members as $troop) { if(!empty($troop['members'])) { ?>
          <div class="container">
              <div class="badge badge<?php echo $troop['id'] ?>"><?php echo $troop['title']; ?></div>
              <table>
                  <tr>
                      <th>Nom</th>
                      <th>Grade</th>
                      <th>Points</th>
                      <th>Présences</th>
                      <th>Plateforme</th>
                      <th>Métiers</th>
                  </tr>
                  <?php foreach($troop['members'] as $member) { ?>
                  <tr class="member" onclick="window.location.href = '/profil/<?php echo $member->user_id ?>'">
                      <td><?php echo $member->username ?></td>
                      <td><img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt=""></td>
                      <td><?php echo $member->panel_pts ?></td>
                      <td><?php echo $member->panel_prs ?>/<?php echo $member->panel_abs ?></td>
                      <td>
                          <?php if ($member->platform_icon !== null) { ?>
                          <img class="platform-icon" src="/pictures/icons/<?php echo esc($member->platform_icon, 'attr') ?>" alt="<?php echo esc($member->platform) ?>" title="<?php echo esc($member->platform) ?>">
                          <?php } else { ?>
                          <span class="platform-unknown">-</span>
                          <?php } ?>
                      </td>
                      <td>
                          <?php if (!empty($member->metier_badges)) { ?>
                          <div class="metier-badges">
                              <?php foreach ($member->metier_badges as $badge) { ?>
                              <span class="metier-badge<?php echo $badge['chief'] ? ' metier-badge-chief' : '' ?>" style="--metier-color: <?php echo esc($badge['color'], 'attr') ?>" title="<?php echo esc($badge['title']) ?>"><?php echo esc($badge['abbr']) ?></span>
                              <?php } ?>
                          </div>
                          <?php } ?>
                      </td>
                  </tr>
                  <?php } ?>
              </table>
          </div>
          <?php }} ?>
      </div>
    </section>
</main>
