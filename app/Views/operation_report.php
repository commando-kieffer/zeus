<main class="training">
    <form action="/operations/report/<?php echo $operation->id ?>" method="post" class="training-form">
        <div class="tf-head">
            <h1>Rapport de présence</h1>
            <p class="operation-meta"><?php echo esc($operation->name) ?> — <?php echo (new DateTime($operation->date))->format('d/m/Y') ?> — <?php echo esc($operation->location) ?></p>
        </div>
        <div class="tf-content">
            <div class="tfc-container">
                <div class="badge badge<?php echo $troop['id'] ?>"><?php echo $troop['title']; ?></div>
                <table>
                    <tr>
                        <th>Nom</th>
                        <th>Grade</th>
                        <th>Présence</th>
                    </tr>
                    <?php foreach ($troop['members'] as $member) { ?>
                    <tr class="tfcc-member">
                        <td><?php echo $member->username ?></td>
                        <td><img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt=""></td>
                        <td><input name="<?php echo $member->user_id ?>" type="checkbox"></td>
                    </tr>
                    <?php } ?>
                </table>
            </div>
        </div>
        <div class="tf-foot">
            <button type="submit" class="outline-btn-inverse">ENVOYER LE RAPPORT</button>
        </div>
    </form>
</main>
