<main class="training">
    <form action="/points/add_correct_point" method="post" class="training-form">
        <div class="tf-head">
            <h1>Corriger des points</h1>
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
                        <td><input name="<?php echo $member->user_id ?>" value="0" type="number"></td>
                    </tr>
                    <?php } ?>
                </table>
            </div>
            <?php }} ?>
        </div>
        <div class="tf-foot">
            <button type="submit" class="outline-btn-inverse">ENVOYER LES MODIFICATIONS</button>
        </div>
    </form>
</main>