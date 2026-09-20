<main class="vault">
    <section class="vault-head">
        <h1>Coffre</h1>
        <?php if ($enrolled && $is_cptc) { ?>
        <button type="button" class="outline-btn-inverse" id="vault-new-trigger">Nouvelle entrée</button>
        <?php } ?>
    </section>

    <p class="vault-alert vault-alert-error" id="vault-insecure" hidden>
        Le chiffrement du navigateur est indisponible. Le coffre exige une connexion HTTPS.
    </p>

    <div class="vault-alert" id="vault-notice" hidden></div>

    <?php if (!$enrolled) { ?>

    <section class="vault-panel vault-enrol">
        <h2>Créer votre clé de coffre</h2>
        <p>
            Avant d'accéder au coffre, vous devez créer votre paire de clés. Elle est générée
            dans votre navigateur&nbsp;: votre mot de passe de coffre ne quitte jamais cet
            ordinateur et n'est jamais transmis au serveur.
        </p>
        <p class="vault-warning">
            Ce mot de passe est personnel et <strong>irrécupérable</strong>. Si vous l'oubliez,
            votre clé privée est perdue définitivement&nbsp;: il faudra recréer une clé, et
            demander qu'on vous repartage chaque entrée.
        </p>

        <div class="vault-field">
            <label for="enrol-password">Mot de passe de coffre</label>
            <input type="password" id="enrol-password" autocomplete="new-password" spellcheck="false">
        </div>
        <div class="vault-field">
            <label for="enrol-password-confirm">Confirmation</label>
            <input type="password" id="enrol-password-confirm" autocomplete="new-password" spellcheck="false">
        </div>

        <button type="button" class="outline-btn-inverse" id="enrol-submit">Créer ma clé</button>
        <p class="vault-progress" id="enrol-progress" hidden>Génération de la clé en cours, patientez…</p>
    </section>

    <?php } else { ?>

    <section class="vault-panel">
        <?php if ($entries === []) { ?>
        <p class="empty-state">Aucune entrée dans le coffre.</p>
        <?php } else { ?>
        <table class="vault-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Mot de passe</th>
                    <th>Créée le</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry) { ?>
                <tr data-entry-id="<?php echo esc($entry->id) ?>">
                    <td class="vault-name" data-label="Nom"><?php echo esc($entry->name) ?></td>
                    <td class="vault-secret" data-role="secret" data-label="Mot de passe"><span class="vault-placeholder"></span></td>
                    <td class="vault-date" data-label="Créée le"><?php echo esc(date('d/m/Y', strtotime($entry->creation_date))) ?></td>
                    <td class="vault-action" data-label="Actions">
                        <?php if ($entry->authorized) { ?>
                        <button type="button" class="outline-btn-inverse vault-reveal" data-entry-id="<?php echo esc($entry->id) ?>">Afficher</button>
                        <?php } else { ?>
                        <span class="vault-locked" title="Vous n'avez pas accès à cette entrée">Sans accès</span>
                        <?php } ?>
                        <?php if ($is_cptc && $entry->authorized) { ?>
                        <button type="button" class="outline-btn-inverse vault-edit" data-entry-id="<?php echo esc($entry->id) ?>">Modifier</button>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } ?>
    </section>

    <!-- Saisie du mot de passe de coffre, à chaque déchiffrement. -->
    <div class="vault-modal" id="unlock-modal" hidden>
        <div class="vault-modal-content">
            <h2>Déverrouiller</h2>
            <p id="unlock-entry-name"></p>
            <div class="vault-field">
                <label for="unlock-password">Votre mot de passe de coffre</label>
                <input type="password" id="unlock-password" autocomplete="off" spellcheck="false">
            </div>
            <p class="vault-error" id="unlock-error" hidden></p>
            <p class="vault-progress" id="unlock-progress" hidden>Déchiffrement…</p>
            <div class="vault-modal-actions">
                <button type="button" class="outline-btn-inverse" id="unlock-cancel">Annuler</button>
                <button type="button" class="outline-btn-inverse" id="unlock-submit">Afficher</button>
            </div>
        </div>
    </div>

    <?php if ($is_cptc) { ?>
    <!-- Création d'une entrée, réservée au Commandant.
         Le chiffrement n'utilise que les clés publiques des destinataires,
         mais le mot de passe de coffre reste nécessaire : chaque autorisation
         doit être signée avec la clé privée de signature du Commandant. -->
    <div class="vault-modal" id="new-modal" hidden>
        <div class="vault-modal-content">
            <h2>Nouvelle entrée</h2>
            <div class="vault-field">
                <label for="new-name">Nom</label>
                <input type="text" id="new-name" maxlength="128" spellcheck="false">
            </div>
            <div class="vault-field">
                <label for="new-secret">Mot de passe à protéger</label>
                <input type="password" id="new-secret" autocomplete="new-password" spellcheck="false">
            </div>
            <div class="vault-field">
                <span class="vault-field-label">Qui peut le lire</span>
                <ul class="vault-recipients">
                    <?php foreach ($recipients as $recipient) { ?>
                    <li>
                        <label>
                            <input type="checkbox" class="vault-recipient" value="<?php echo (int) $recipient['user_id'] ?>"
                                <?php echo $recipient['mandatory'] ? 'checked disabled' : '' ?>>
                            <?php echo esc($recipient['username']) ?>
                            <?php if ($recipient['mandatory']) { ?>
                            <span class="vault-mandatory"><?php echo $recipient['user_id'] === $current_user_id ? 'vous' : 'Commandant' ?></span>
                            <?php } ?>
                        </label>
                    </li>
                    <?php } ?>
                </ul>
                <p class="vault-hint">
                    Seul l'état-major déjà enrôlé apparaît ici&nbsp;: partager une entrée suppose
                    de disposer de la clé publique du destinataire.
                </p>
            </div>
            <div class="vault-field">
                <label for="new-password">Votre mot de passe de coffre</label>
                <input type="password" id="new-password" autocomplete="off" spellcheck="false">
            </div>
            <p class="vault-hint">
                Il sert à signer les autorisations. Le serveur vérifie cette signature
                avec votre clé publique&nbsp;: c'est ce qui garantit qu'un accès ne peut
                être accordé par personne d'autre que vous.
            </p>
            <p class="vault-error" id="new-error" hidden></p>
            <p class="vault-progress" id="new-progress" hidden>Chiffrement…</p>
            <div class="vault-modal-actions">
                <button type="button" class="outline-btn-inverse" id="new-cancel">Annuler</button>
                <button type="button" class="outline-btn-inverse" id="new-submit">Enregistrer</button>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php if ($is_cptc) { ?>
    <!-- Administration, réservée au Commandant. Toute modification renouvelle la clé
         de l'entrée : un accès retiré cesse réellement d'en être un. -->
    <div class="vault-modal" id="edit-modal" hidden>
        <div class="vault-modal-content">
            <h2>Modifier</h2>
            <p id="edit-entry-name"></p>
            <div class="vault-field">
                <label for="edit-secret">Nouveau mot de passe</label>
                <input type="password" id="edit-secret" autocomplete="new-password" spellcheck="false"
                       placeholder="laisser vide pour conserver l'actuel">
            </div>
            <div class="vault-field">
                <span class="vault-field-label">Qui peut le lire</span>
                <ul class="vault-recipients" id="edit-recipients">
                    <?php foreach ($recipients as $recipient) { ?>
                    <li>
                        <label>
                            <input type="checkbox" class="vault-edit-recipient" value="<?php echo (int) $recipient['user_id'] ?>"
                                <?php echo $recipient['user_id'] === $current_user_id ? 'checked disabled' : '' ?>>
                            <?php echo esc($recipient['username']) ?>
                            <?php if ($recipient['user_id'] === $current_user_id) { ?>
                            <span class="vault-mandatory">vous, Commandant</span>
                            <?php } ?>
                        </label>
                    </li>
                    <?php } ?>
                </ul>
            </div>
            <div class="vault-field">
                <label for="edit-password">Votre mot de passe de coffre</label>
                <input type="password" id="edit-password" autocomplete="off" spellcheck="false">
            </div>
            <p class="vault-hint">
                Il est requis même pour un simple retrait d'accès&nbsp;: l'entrée doit être
                rouverte puis re-chiffrée sous une clé neuve, faute de quoi le retrait
                resterait sans effet pour qui aurait gardé une copie de la base.
            </p>
            <p class="vault-error" id="edit-error" hidden></p>
            <p class="vault-progress" id="edit-progress" hidden>Re-chiffrement…</p>
            <div class="vault-modal-actions">
                <button type="button" class="outline-btn-inverse" id="edit-cancel">Annuler</button>
                <button type="button" class="outline-btn-inverse" id="edit-submit">Enregistrer</button>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php } ?>
</main>

<script>
(function () {
    'use strict';

    const VAULT = {
        enrolled: <?php echo json_encode((bool) $enrolled) ?>,
        ownKeys: <?php echo json_encode($own_keys) ?>,
        recipients: <?php echo json_encode($recipients) ?>,
        authorizations: <?php echo json_encode((object) $authorizations) ?>,
        isCptc: <?php echo json_encode((bool) $is_cptc) ?>,
        userId: <?php echo (int) $current_user_id ?>,
        kdfIterations: <?php echo (int) $kdf_iterations ?>,
        csrf: <?php echo json_encode(csrf_hash()) ?>,
        csrfHeader: 'X-CSRF-TOKEN'
    };

    // Durée d'affichage d'un mot de passe en clair avant rechargement.
    const REVEAL_SECONDS = 30;

    // WebCrypto n'existe pas hors contexte sécurisé. Mieux vaut le dire
    // franchement que laisser la page échouer par morceaux.
    if (!window.crypto || !window.crypto.subtle) {
        document.getElementById('vault-insecure').hidden = false;
        return;
    }

    const subtle = window.crypto.subtle;
    const encoder = new TextEncoder();
    const decoder = new TextDecoder();

    const enc = (s) => encoder.encode(s);

    function bytesToHex(bytes) {
        let out = '';
        for (let i = 0; i < bytes.length; i++) {
            out += bytes[i].toString(16).padStart(2, '0');
        }
        return out;
    }

    function hexToBytes(hex) {
        const out = new Uint8Array(hex.length / 2);
        for (let i = 0; i < out.length; i++) {
            out[i] = parseInt(hex.substr(i * 2, 2), 16);
        }
        return out;
    }

    async function post(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/json',
                [VAULT.csrfHeader]: VAULT.csrf
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json().catch(() => ({ ok: false, error: 'Réponse illisible du serveur.' }));

        // Le jeton est régénéré à chaque soumission : sans cette reprise, le
        // deuxième appel de la page échouerait systématiquement.
        if (data.csrf) {
            VAULT.csrf = data.csrf;
        }

        return data;
    }

    // -----------------------------------------------------------------
    //  Dérivation et clés
    // -----------------------------------------------------------------

    /**
     * KEK = PBKDF2-SHA256(mot de passe personnel, sel, itérations).
     *
     * C'est le seul endroit du système où une donnée à faible entropie est
     * transformée en clé, donc le seul endroit où un coût de calcul élevé
     * achète quelque chose. La clé est non extractible : ses octets
     * n'apparaissent jamais dans le tas JavaScript.
     */
    async function deriveKek(passwordBytes, saltBytes, iterations) {
        const base = await subtle.importKey('raw', passwordBytes, 'PBKDF2', false, ['deriveKey']);

        return subtle.deriveKey(
            { name: 'PBKDF2', salt: saltBytes, iterations: iterations, hash: 'SHA-256' },
            base,
            { name: 'AES-GCM', length: 256 },
            false,
            ['encrypt', 'decrypt']
        );
    }

    function privateKeyAad() {
        return enc('ck-vault:privkey:v1|' + VAULT.userId);
    }

    // AAD distincte de celle de la clé de chiffrement : les deux blobs sont
    // protégés par la même KEK, cette étiquette empêche de faire passer l'un
    // pour l'autre.
    function signKeyAad() {
        return enc('ck-vault:signkey:v1|' + VAULT.userId);
    }

    function entryAad(name) {
        return enc('ck-vault:entry:v1|' + name);
    }

    /**
     * Message canonique d'une autorisation. Doit correspondre, octet pour
     * octet, à VaultCrypto::grant_message() côté PHP.
     */
    function grantMessage(entryId, userId, wrappedDekHex) {
        return enc('ck-vault:grant:v1|' + entryId.toLowerCase() + '|' + userId + '|' + wrappedDekHex.toLowerCase());
    }

    /**
     * Déchiffre la clé privée du membre avec son mot de passe.
     *
     * Renvoie null si le mot de passe est erroné : c'est l'échec du tag GCM
     * qui le révèle, entièrement côté navigateur. Aucun appel au serveur n'est
     * nécessaire pour éprouver un mot de passe, ce qui prive un attaquant de
     * tout oracle en ligne.
     */
    async function unlockKek(password) {
        const passwordBytes = enc(password);
        const kek = await deriveKek(
            passwordBytes,
            hexToBytes(VAULT.ownKeys.kdf_salt),
            parseInt(VAULT.ownKeys.kdf_iterations, 10)
        );
        passwordBytes.fill(0);

        return kek;
    }

    /**
     * Ouvre une clé privée stockée et l'importe pour l'usage indiqué.
     *
     * Renvoie null si le mot de passe est erroné : c'est l'échec du tag GCM
     * qui le révèle. L'import est fait NON extractible, donc une fois la clé
     * en place ses octets ne peuvent plus ressortir du sous-système
     * cryptographique du navigateur.
     */
    async function openPrivateKey(kek, blobHex, nonceHex, aad, algorithm, usages) {
        let pkcs8;
        try {
            pkcs8 = new Uint8Array(await subtle.decrypt(
                { name: 'AES-GCM', iv: hexToBytes(nonceHex), additionalData: aad, tagLength: 128 },
                kek,
                hexToBytes(blobHex)
            ));
        } catch (e) {
            return null;
        }

        const key = await subtle.importKey('pkcs8', pkcs8, algorithm, false, usages);
        pkcs8.fill(0);

        return key;
    }

    async function unlockPrivateKey(password) {
        return openPrivateKey(
            await unlockKek(password),
            VAULT.ownKeys.private_key_enc,
            VAULT.ownKeys.private_key_nonce,
            privateKeyAad(),
            { name: 'RSA-OAEP', hash: 'SHA-256' },
            ['decrypt']
        );
    }

    /**
     * Clé privée de SIGNATURE. Seule celle du Commandant produit des
     * autorisations que le serveur accepte.
     */
    async function unlockSigningKey(password) {
        return openPrivateKey(
            await unlockKek(password),
            VAULT.ownKeys.sign_private_key_enc,
            VAULT.ownKeys.sign_private_key_nonce,
            signKeyAad(),
            { name: 'RSASSA-PKCS1-v1_5', hash: 'SHA-256' },
            ['sign']
        );
    }

    // -----------------------------------------------------------------
    //  Enrôlement
    // -----------------------------------------------------------------

    /**
     * Chiffre une clé privée exportée sous la KEK du membre.
     *
     * @returns {{enc: string, nonce: string}} en hexadécimal
     */
    async function wrapPrivateKey(kek, pkcs8, aad) {
        const nonce = window.crypto.getRandomValues(new Uint8Array(12));
        const encrypted = new Uint8Array(await subtle.encrypt(
            { name: 'AES-GCM', iv: nonce, additionalData: aad, tagLength: 128 },
            kek,
            pkcs8
        ));

        return { enc: bytesToHex(encrypted), nonce: bytesToHex(nonce) };
    }

    /**
     * Crée les DEUX paires de clés du membre.
     *
     * Une clé WebCrypto est liée à son algorithme : une clé RSA-OAEP sert
     * uniquement à chiffrer, et ne peut pas signer. Il faut donc une seconde
     * paire, dédiée à la signature, pour que le Commandant puisse attester des
     * autorisations. Les deux clés privées sont protégées par la même KEK,
     * mais avec des AAD distinctes — de sorte qu'aucune ne puisse être
     * substituée à l'autre.
     */
    async function enrol(password) {
        const salt = window.crypto.getRandomValues(new Uint8Array(32));
        const passwordBytes = enc(password);
        const kek = await deriveKek(passwordBytes, salt, VAULT.kdfIterations);
        passwordBytes.fill(0);

        const cipherPair = await subtle.generateKey(
            { name: 'RSA-OAEP', modulusLength: 3072, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-256' },
            true,
            ['encrypt', 'decrypt']
        );

        // RSASSA-PKCS1-v1_5 plutôt que RSA-PSS : openssl_verify(), côté PHP,
        // n'applique que ce bourrage et n'offre aucun réglage.
        const signPair = await subtle.generateKey(
            { name: 'RSASSA-PKCS1-v1_5', modulusLength: 3072, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-256' },
            true,
            ['sign', 'verify']
        );

        const spki = new Uint8Array(await subtle.exportKey('spki', cipherPair.publicKey));
        const pkcs8 = new Uint8Array(await subtle.exportKey('pkcs8', cipherPair.privateKey));
        const signSpki = new Uint8Array(await subtle.exportKey('spki', signPair.publicKey));
        const signPkcs8 = new Uint8Array(await subtle.exportKey('pkcs8', signPair.privateKey));

        const wrappedCipher = await wrapPrivateKey(kek, pkcs8, privateKeyAad());
        const wrappedSign = await wrapPrivateKey(kek, signPkcs8, signKeyAad());

        pkcs8.fill(0);
        signPkcs8.fill(0);

        return post('/coffre/enrol', {
            public_key: bytesToHex(spki),
            private_key_enc: wrappedCipher.enc,
            private_key_nonce: wrappedCipher.nonce,
            sign_public_key: bytesToHex(signSpki),
            sign_private_key_enc: wrappedSign.enc,
            sign_private_key_nonce: wrappedSign.nonce,
            kdf_salt: bytesToHex(salt),
            kdf_iterations: VAULT.kdfIterations
        });
    }

    // -----------------------------------------------------------------
    //  Création
    // -----------------------------------------------------------------

    async function createEntry(name, secret, recipientIds, password) {
        const signingKey = await unlockSigningKey(password);
        if (signingKey === null) {
            return { ok: false, error: 'Mot de passe de coffre incorrect.' };
        }

        // L'identifiant est tiré ici, avant la signature : il entre dans le
        // message signé, il doit donc être connu au moment de signer.
        const entryId = newEntryId();
        const sealed = await sealForRecipients(entryId, name, secret, recipientIds, signingKey);

        return post('/coffre/create', {
            entry_id: entryId,
            name: name,
            nonce: sealed.nonce,
            cipher: sealed.cipher,
            keys: sealed.keys
        });
    }

    // -----------------------------------------------------------------
    //  Déchiffrement
    // -----------------------------------------------------------------

    async function revealEntry(entryId, password) {
        // La clé privée est ouverte AVANT tout appel réseau : un mot de passe
        // erroné ne coûte donc aucune requête et n'apprend rien au serveur.
        const privateKey = await unlockPrivateKey(password);
        if (privateKey === null) {
            await post('/coffre/report_failure', { entry_id: entryId });
            return { ok: false, error: 'Mot de passe de coffre incorrect.' };
        }

        const response = await post('/coffre/decrypt', { entry_id: entryId });
        if (!response.ok) {
            return response;
        }

        let dekRaw;
        try {
            dekRaw = await subtle.decrypt({ name: 'RSA-OAEP' }, privateKey, hexToBytes(response.wrapped_dek));
        } catch (e) {
            await post('/coffre/report_failure', { entry_id: entryId });
            return { ok: false, error: "La clé de cette entrée ne correspond pas à votre clé privée." };
        }

        const dek = await subtle.importKey('raw', dekRaw, { name: 'AES-GCM' }, false, ['decrypt']);

        let plain;
        try {
            plain = await subtle.decrypt(
                {
                    name: 'AES-GCM',
                    iv: hexToBytes(response.nonce),
                    additionalData: entryAad(response.name),
                    tagLength: 128
                },
                dek,
                hexToBytes(response.cipher)
            );
        } catch (e) {
            // Le nom de l'entrée est lié en AAD : cet échec signale des données
            // altérées ou un chiffré déplacé d'une entrée vers une autre.
            await post('/coffre/report_failure', { entry_id: entryId });
            return { ok: false, error: "Données altérées : le contenu ne correspond pas à cette entrée." };
        }

        await post('/coffre/report_success', {});

        return { ok: true, secret: decoder.decode(plain) };
    }

    // -----------------------------------------------------------------
    //  Modification (Commandant)
    // -----------------------------------------------------------------

    /**
     * Chiffre un secret sous une DEK NEUVE et l'encapsule pour chaque
     * destinataire retenu. Partagé par la création et la modification : c'est
     * exactement la même opération, à ceci près que la modification écrase une
     * entrée existante au lieu d'en créer une.
     */
    async function sealForRecipients(entryId, name, secret, recipientIds, signingKey) {
        const dekBytes = window.crypto.getRandomValues(new Uint8Array(32));
        const dek = await subtle.importKey('raw', dekBytes, { name: 'AES-GCM' }, false, ['encrypt']);
        const nonce = window.crypto.getRandomValues(new Uint8Array(12));

        const cipher = new Uint8Array(await subtle.encrypt(
            { name: 'AES-GCM', iv: nonce, additionalData: entryAad(name), tagLength: 128 },
            dek,
            enc(secret)
        ));

        const keys = [];
        for (const recipient of VAULT.recipients) {
            if (recipientIds.indexOf(recipient.user_id) === -1) {
                continue;
            }

            const publicKey = await subtle.importKey(
                'spki', hexToBytes(recipient.public_key),
                { name: 'RSA-OAEP', hash: 'SHA-256' }, false, ['encrypt']
            );

            const wrapped = bytesToHex(new Uint8Array(
                await subtle.encrypt({ name: 'RSA-OAEP' }, publicKey, dekBytes)
            ));

            // Chaque autorisation est signée séparément, et la signature
            // couvre l'entrée, le destinataire et la clé encapsulée. Aucun des
            // trois ne peut donc être changé après coup, et une autorisation
            // ne peut pas être transposée d'une entrée ou d'un membre à un
            // autre.
            const signature = bytesToHex(new Uint8Array(await subtle.sign(
                { name: 'RSASSA-PKCS1-v1_5' },
                signingKey,
                grantMessage(entryId, recipient.user_id, wrapped)
            )));

            keys.push({ user_id: recipient.user_id, wrapped_dek: wrapped, signature: signature });
        }

        dekBytes.fill(0);

        return { nonce: bytesToHex(nonce), cipher: bytesToHex(cipher), keys: keys };
    }

    /** Identifiant d'entrée : 16 octets du générateur cryptographique. */
    function newEntryId() {
        return bytesToHex(window.crypto.getRandomValues(new Uint8Array(16)));
    }

    /**
     * Le nom n'est jamais modifiable : il est lié en AAD de la couche client,
     * le changer rendrait l'entrée indéchiffrable.
     */
    async function updateEntry(entryId, name, newSecret, recipientIds, password) {
        const privateKey = await unlockPrivateKey(password);
        if (privateKey === null) {
            await post('/coffre/report_failure', { entry_id: entryId });
            return { ok: false, error: 'Mot de passe de coffre incorrect.' };
        }

        const signingKey = await unlockSigningKey(password);
        if (signingKey === null) {
            return { ok: false, error: 'Clé de signature illisible. Recréez votre clé de coffre.' };
        }

        let secret = newSecret;

        // Conserver le mot de passe actuel suppose de le relire : le serveur
        // ne peut pas le recopier lui-même d'une DEK à l'autre, il ne l'a
        // jamais eu en clair.
        if (secret === '') {
            const current = await post('/coffre/decrypt', { entry_id: entryId });
            if (!current.ok) {
                return current;
            }

            const dekRaw = await subtle.decrypt({ name: 'RSA-OAEP' }, privateKey, hexToBytes(current.wrapped_dek));
            const dek = await subtle.importKey('raw', dekRaw, { name: 'AES-GCM' }, false, ['decrypt']);
            const plain = await subtle.decrypt(
                {
                    name: 'AES-GCM',
                    iv: hexToBytes(current.nonce),
                    additionalData: entryAad(current.name),
                    tagLength: 128
                },
                dek,
                hexToBytes(current.cipher)
            );
            secret = decoder.decode(plain);
        }

        const sealed = await sealForRecipients(entryId, name, secret, recipientIds, signingKey);

        return post('/coffre/update', {
            entry_id: entryId,
            nonce: sealed.nonce,
            cipher: sealed.cipher,
            keys: sealed.keys
        });
    }

    // -----------------------------------------------------------------
    //  Interface
    // -----------------------------------------------------------------

    const notice = document.getElementById('vault-notice');

    function showNotice(message, isError) {
        notice.textContent = message;
        notice.classList.toggle('vault-alert-error', !!isError);
        notice.hidden = false;
    }

    /**
     * Remplit les cases « mot de passe » de valeurs factices.
     *
     * Longueur tirée au hasard, sans aucun rapport avec le secret réel : une
     * longueur fidèle divulguerait une information exploitable.
     */
    function paintPlaceholders() {
        const alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
        document.querySelectorAll('.vault-placeholder').forEach(function (node) {
            const length = 10 + Math.floor(Math.random() * 11);
            const picks = window.crypto.getRandomValues(new Uint8Array(length));
            let text = '';
            for (let i = 0; i < length; i++) {
                text += alphabet[picks[i] % alphabet.length];
            }
            node.textContent = text;
        });
    }

    // --- Enrôlement ---

    const enrolSubmit = document.getElementById('enrol-submit');
    if (enrolSubmit) {
        enrolSubmit.addEventListener('click', async function () {
            const password = document.getElementById('enrol-password').value;
            const confirm = document.getElementById('enrol-password-confirm').value;
            const progress = document.getElementById('enrol-progress');

            if (password.length < 12) {
                showNotice('Choisissez un mot de passe d’au moins 12 caractères.', true);
                return;
            }
            if (password !== confirm) {
                showNotice('Les deux saisies diffèrent.', true);
                return;
            }

            enrolSubmit.disabled = true;
            progress.hidden = false;
            notice.hidden = true;

            try {
                const result = await enrol(password);
                if (result.ok) {
                    window.location.reload();
                    return;
                }
                showNotice(result.error || 'Échec de l’enrôlement.', true);
            } catch (e) {
                showNotice('Échec de la génération de la clé : ' + e.message, true);
            }

            document.getElementById('enrol-password').value = '';
            document.getElementById('enrol-password-confirm').value = '';
            enrolSubmit.disabled = false;
            progress.hidden = true;
        });
    }

    // --- Déverrouillage ---

    const unlockModal = document.getElementById('unlock-modal');
    let pendingEntryId = null;
    let countdownTimer = null;

    function closeUnlockModal() {
        if (!unlockModal) {
            return;
        }
        unlockModal.hidden = true;
        document.getElementById('unlock-password').value = '';
        document.getElementById('unlock-error').hidden = true;
        document.getElementById('unlock-progress').hidden = true;
        pendingEntryId = null;
    }

    function displaySecret(entryId, secret) {
        const row = document.querySelector('tr[data-entry-id="' + entryId + '"]');
        if (!row) {
            return;
        }

        const cell = row.querySelector('[data-role="secret"]');
        cell.textContent = secret;
        cell.classList.add('vault-secret-clear');

        const button = row.querySelector('.vault-reveal');
        let remaining = REVEAL_SECONDS;

        // Le bouton change de rôle : il masque désormais au lieu d'afficher.
        // Ce marqueur est lu par l'unique gestionnaire de clic, plus bas.
        button.dataset.revealed = '1';
        button.textContent = 'Masquer (' + remaining + ')';

        countdownTimer = window.setInterval(function () {
            remaining -= 1;
            button.textContent = 'Masquer (' + remaining + ')';
            if (remaining <= 0) {
                hideAndReload();
            }
        }, 1000);
    }

    /**
     * Le rechargement détruit tout le tas JavaScript du document, ce qui est le
     * mieux qu'on puisse faire : une chaîne JavaScript est immuable et ne peut
     * pas être effacée sur place.
     */
    function hideAndReload() {
        window.clearInterval(countdownTimer);
        window.location.reload();
    }

    document.querySelectorAll('.vault-reveal').forEach(function (button) {
        // Un SEUL gestionnaire pour les deux rôles du bouton. En ajouter un
        // second à l'affichage ne remplacerait pas celui-ci : les deux se
        // déclencheraient, et la fenêtre modale s'ouvrirait brièvement avant
        // le rechargement.
        button.addEventListener('click', function () {
            if (button.dataset.revealed === '1') {
                hideAndReload();

                return;
            }

            pendingEntryId = button.getAttribute('data-entry-id');
            const row = button.closest('tr');
            document.getElementById('unlock-entry-name').textContent = row.querySelector('.vault-name').textContent;
            unlockModal.hidden = false;
            document.getElementById('unlock-password').focus();
        });
    });

    const unlockSubmit = document.getElementById('unlock-submit');
    if (unlockSubmit) {
        unlockSubmit.addEventListener('click', async function () {
            const passwordField = document.getElementById('unlock-password');
            const password = passwordField.value;
            const error = document.getElementById('unlock-error');
            const progress = document.getElementById('unlock-progress');
            const entryId = pendingEntryId;

            if (password === '') {
                return;
            }

            unlockSubmit.disabled = true;
            error.hidden = true;
            progress.hidden = false;

            let result;
            try {
                result = await revealEntry(entryId, password);
            } catch (e) {
                result = { ok: false, error: 'Erreur de déchiffrement : ' + e.message };
            }

            // Le champ est vidé quoi qu'il arrive : le nœud du DOM ne doit pas
            // conserver la saisie, même si la chaîne elle-même survit en
            // mémoire jusqu'au passage du ramasse-miettes.
            passwordField.value = '';
            unlockSubmit.disabled = false;
            progress.hidden = true;

            if (result.ok) {
                closeUnlockModal();
                displaySecret(entryId, result.secret);
                return;
            }

            error.textContent = result.error || 'Échec du déchiffrement.';
            error.hidden = false;

            if (result.blocked) {
                error.textContent = 'Trop d’échecs consécutifs : cette adresse IP est désormais bloquée.';
            }
        });

        document.getElementById('unlock-cancel').addEventListener('click', closeUnlockModal);
        document.getElementById('unlock-password').addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                unlockSubmit.click();
            }
        });
    }

    // --- Création ---

    const newModal = document.getElementById('new-modal');
    const newTrigger = document.getElementById('vault-new-trigger');

    if (newTrigger && newModal) {
        newTrigger.addEventListener('click', function () {
            newModal.hidden = false;
            document.getElementById('new-name').focus();
        });

        document.getElementById('new-cancel').addEventListener('click', function () {
            newModal.hidden = true;
            document.getElementById('new-name').value = '';
            document.getElementById('new-secret').value = '';
            document.getElementById('new-password').value = '';
            document.getElementById('new-error').hidden = true;
        });

        const newSubmit = document.getElementById('new-submit');
        newSubmit.addEventListener('click', async function () {
            const name = document.getElementById('new-name').value.trim();
            const secretField = document.getElementById('new-secret');
            const passwordField = document.getElementById('new-password');
            const secret = secretField.value;
            const error = document.getElementById('new-error');
            const progress = document.getElementById('new-progress');

            if (name === '' || secret === '') {
                error.textContent = 'Renseignez un nom et un mot de passe.';
                error.hidden = false;
                return;
            }

            if (passwordField.value === '') {
                error.textContent = 'Saisissez votre mot de passe de coffre pour signer les accès.';
                error.hidden = false;
                return;
            }

            // Les destinataires obligatoires ont une case cochée ET désactivée,
            // que le navigateur n'inclurait pas dans une soumission classique :
            // on les relit donc explicitement.
            const recipientIds = [];
            document.querySelectorAll('.vault-recipient').forEach(function (box) {
                if (box.checked) {
                    recipientIds.push(parseInt(box.value, 10));
                }
            });

            newSubmit.disabled = true;
            error.hidden = true;
            progress.hidden = false;

            let result;
            try {
                result = await createEntry(name, secret, recipientIds, passwordField.value);
            } catch (e) {
                result = { ok: false, error: 'Erreur de chiffrement : ' + e.message };
            }

            secretField.value = '';
            passwordField.value = '';
            newSubmit.disabled = false;
            progress.hidden = true;

            if (result.ok) {
                window.location.reload();
                return;
            }

            error.textContent = result.error || 'Échec de l’enregistrement.';
            error.hidden = false;
        });
    }

    // --- Modification (Commandant) ---

    const editModal = document.getElementById('edit-modal');
    let editingEntryId = null;
    let editingEntryName = null;

    if (editModal) {
        document.querySelectorAll('.vault-edit').forEach(function (button) {
            button.addEventListener('click', function () {
                editingEntryId = button.getAttribute('data-entry-id');
                editingEntryName = button.closest('tr').querySelector('.vault-name').textContent;

                document.getElementById('edit-entry-name').textContent = editingEntryName;
                document.getElementById('edit-secret').value = '';
                document.getElementById('edit-password').value = '';
                document.getElementById('edit-error').hidden = true;

                // Cases pré-cochées selon les accès réellement en place.
                const current = VAULT.authorizations[editingEntryId] || [];
                document.querySelectorAll('.vault-edit-recipient').forEach(function (box) {
                    if (!box.disabled) {
                        box.checked = current.indexOf(parseInt(box.value, 10)) !== -1;
                    }
                });

                editModal.hidden = false;
                document.getElementById('edit-secret').focus();
            });
        });

        document.getElementById('edit-cancel').addEventListener('click', function () {
            editModal.hidden = true;
            document.getElementById('edit-secret').value = '';
            document.getElementById('edit-password').value = '';
        });

        const editSubmit = document.getElementById('edit-submit');
        editSubmit.addEventListener('click', async function () {
            const secretField = document.getElementById('edit-secret');
            const passwordField = document.getElementById('edit-password');
            const error = document.getElementById('edit-error');
            const progress = document.getElementById('edit-progress');

            if (passwordField.value === '') {
                error.textContent = 'Saisissez votre mot de passe de coffre.';
                error.hidden = false;
                return;
            }

            const recipientIds = [];
            document.querySelectorAll('.vault-edit-recipient').forEach(function (box) {
                if (box.checked) {
                    recipientIds.push(parseInt(box.value, 10));
                }
            });

            editSubmit.disabled = true;
            error.hidden = true;
            progress.hidden = false;

            let result;
            try {
                result = await updateEntry(
                    editingEntryId, editingEntryName, secretField.value, recipientIds, passwordField.value
                );
            } catch (e) {
                result = { ok: false, error: 'Erreur de re-chiffrement : ' + e.message };
            }

            secretField.value = '';
            passwordField.value = '';
            editSubmit.disabled = false;
            progress.hidden = true;

            if (result.ok) {
                window.location.reload();
                return;
            }

            error.textContent = result.error || 'Échec de la modification.';
            error.hidden = false;
        });
    }

    paintPlaceholders();
})();
</script>
