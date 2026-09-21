<main class="training raffle">
    <?php if ($results !== null) { ?>
    <section class="tf-results">
        <h2>Résultats du tirage</h2>
        <div class="raffle-rounds">
            <?php foreach ($results as $i => $round) { ?>
            <div class="raffle-round">
                <h3>Tirage n&deg;<?php echo $i + 1 ?></h3>
                <ul>
                    <?php foreach ($round as $member) { ?>
                    <li>
                        <img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt="">
                        <span class="raffle-name"><?php echo esc($member->username) ?></span>
                        <span class="badge badge<?php echo $member->troop_id ?>"><?php echo esc($member->troop_title) ?></span>
                    </li>
                    <?php } ?>
                </ul>
            </div>
            <?php } ?>
        </div>
    </section>
    <?php } ?>

    <form action="/outils/tirage-au-sort" method="post" class="training-form">
        <div class="tf-head">
            <h1>Tirage au sort</h1>
            <?php if (!empty($errors)) { ?>
            <ul class="form-errors">
                <?php foreach ($errors as $error) { ?><li><?php echo esc($error) ?></li><?php } ?>
            </ul>
            <?php } ?>
            <div class="tf-sub">
                <label class="tf-field">
                    <span>Nombre de tirages</span>
                    <input type="number" name="rounds" min="1" max="100" value="<?php echo $rounds ?>">
                </label>
                <label class="tf-field">
                    <span>Membres tirés au sort par tirage</span>
                    <input type="number" name="players_per_round" min="1" value="<?php echo $players_per_round ?>">
                </label>
            </div>
        </div>
        <div class="tf-content">
            <?php foreach ($troops as $troop) { ?>
            <div class="tfc-container">
                <label class="troop-toggle">
                    <input type="checkbox" name="troop_<?php echo $troop['id'] ?>" value="1" <?php echo $troop['checked'] ? 'checked' : '' ?>>
                    <span class="badge badge<?php echo $troop['id'] ?>"><?php echo esc($troop['title']) ?></span>
                </label>
                <table>
                    <tr>
                        <th>Nom</th>
                        <th>Grade</th>
                        <th>Multiplicateur</th>
                    </tr>
                    <?php foreach ($troop['members'] as $member) { ?>
                    <tr class="tfcc-member">
                        <td><?php echo esc($member->username) ?></td>
                        <td><img src="/pictures/jackets/<?php echo $member->user_group_id ?>.png" alt=""></td>
                        <td><input type="number" class="multiplier-input" name="multiplier_<?php echo $member->user_id ?>" min="0" step="0.1" value="<?php echo $member->multiplier ?>"></td>
                    </tr>
                    <?php } ?>
                </table>
            </div>
            <?php } ?>
        </div>
        <div class="tf-foot">
            <button type="submit" class="outline-btn-inverse">LANCER LE TIRAGE</button>
        </div>
    </form>
</main>
