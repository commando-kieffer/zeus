<main class="training">
    <form action="/points/add_training" method="post" class="training-form">
        <div class="tf-head">
            <h1>Rédiger un rapport de training</h1>
            <div class="tf-sub">
                <input type="text" name="title" placeholder="Titre opération (ex. : Opération Armaguedon)" required>
                <input type="date" name="date" required>
            </div>
        </div>
        <div class="tf-content">
            <?php foreach ($members as $troop) { if(!empty($troop['members'])) { ?>
            <div class="tfc-container">
                <div class="badge badge<?php echo $troop['id'] ?>"><?php echo $troop['title']; ?></div>
                <table>
                    <tr>
                        <th>Nom</th>
                        <th>Grade</th>
                        <th>Présence</th>
                    </tr>
                    <?php foreach($troop['members'] as $member) { ?>
                    <tr class="tfcc-member">
                        <td><?php echo $member->username ?></td>
                        <td><img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt=""></td>
                        <td><input name="<?php echo $member->user_id ?>" type="checkbox"></td>
                    </tr>
                    <?php } ?>
                </table>
            </div>
            <?php }} ?>
        </div>
        <div class="tf-foot">
            <button type="submit" class="outline-btn-inverse">ENVOYER LE RAPPORT</button>
        </div>
    </form>
</main>