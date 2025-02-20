<main class="home">
    <section class="welcome">
        <h1>Bienvenue sur Zeus</h1>
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
                      <th>Absences</th>
                  </tr>
                  <?php foreach($troop['members'] as $member) { ?>
                  <tr class="member" onclick="window.location.href = '/profil/<?php echo $member->user_id ?>'">
                      <td><?php echo $member->username ?></td>
                      <td><img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt=""></td>
                      <td><?php echo $member->panel_pts ?></td>
                      <td><?php echo $member->panel_prs ?></td>
                      <td><?php echo $member->panel_abs ?></td>
                  </tr>
                  <?php } ?>
              </table>
          </div>
          <?php }} ?>
      </div>
    </section>
</main>
