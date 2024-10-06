<main class="training">
    <form action="/points/modify_training_presence" method="post" class="training-form">
        <div class="tf-head">
            <h1>Modifier un rapport de training</h1>
            <div class="tf-sub">
                <input type="text" name="title" placeholder="Titre opération (ex. : Opération Armaguedon)" value="<?php echo $training[0]->title ?>" readonly>
                <input type="date" name="date" value="<?php echo $training[0]->date ?>" readonly>
                <input type="hidden" name="id" value="<?php echo $training[0]->id ?>">
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
                        <td><input name="<?php echo $member->user_id ?>" type="checkbox" <?php 
                            foreach($presence as $is_here) {
                                if ($is_here->id_user == $member->user_id)
                                    echo 'checked';
                            }
                        ?>></td>
                    </tr>
                    <?php } ?>
                </table>
            </div>
            <?php }} ?>
        </div>
        <div class="tf-foot">
            <button type="submit" class="outline-btn-inverse">ENVOYER LA CORRECTION</button>
        </div>
    </form>
</main>