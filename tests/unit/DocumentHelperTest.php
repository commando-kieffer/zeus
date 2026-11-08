<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DocumentHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper(['document', 'date', 'role']);
    }

    public function testFormatSizeUsesFrenchUnitsAndDropsUselessDecimals(): void
    {
        $this->assertSame('0 o', document_format_size(0));
        $this->assertSame('512 o', document_format_size(512));
        $this->assertSame('1 Ko', document_format_size(1024));
        $this->assertSame('1,5 Ko', document_format_size(1536));
        $this->assertSame('150 Ko', document_format_size(150 * 1024));
        $this->assertSame('1,1 Mo', document_format_size(1191546));
        $this->assertSame('2 Mo', document_format_size(2 * 1024 * 1024));
        $this->assertSame('1,5 Go', document_format_size((int) (1.5 * 1024 ** 3)));
        $this->assertSame('0 o', document_format_size(-5));
    }

    public function testFileLabelIsTheUppercasedExtensionWhenItLooksLikeOne(): void
    {
        $this->assertSame('PDF', document_file_label('ck-livret-recruteurs.pdf'));
        $this->assertSame('ZIP', document_file_label('archive.final.zip'));
        $this->assertSame('PNG', document_file_label('Carte.PNG'));
        $this->assertSame('FICHIER', document_file_label('LISEZMOI'));
        $this->assertSame('FICHIER', document_file_label('nom.tropLong'));
        $this->assertSame('FICHIER', document_file_label('nom.é'));
        $this->assertSame('FICHIER', document_file_label(''));
    }

    public function testOnlyPdfAndRasterImagesAreServedInline(): void
    {
        foreach (['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'IMAGE/PNG'] as $mime) {
            $this->assertTrue(document_is_inline_mime($mime), $mime);
        }

        // Ces types peuvent contenir du script : ils sont toujours téléchargés.
        foreach (['text/html', 'image/svg+xml', 'application/xhtml+xml', 'text/xml', 'application/zip', 'application/octet-stream', ''] as $mime) {
            $this->assertFalse(document_is_inline_mime($mime), $mime);
        }
    }

    public function testCleanFileNameKeepsOrdinaryNamesUntouched(): void
    {
        $this->assertSame('ck-livret-recruteurs.pdf', document_clean_file_name('ck-livret-recruteurs.pdf'));
        $this->assertSame('Livret éclaireur (v2).pdf', document_clean_file_name('Livret éclaireur (v2).pdf'));
    }

    public function testCleanFileNameStripsPathsAndControlCharacters(): void
    {
        $this->assertSame('passwd', document_clean_file_name('../../etc/passwd'));
        $this->assertSame('secret.txt', document_clean_file_name('C:\\Users\\moi\\secret.txt'));
        // Un retour à la ligne dans le nom permettrait d'injecter un en-tête HTTP.
        $this->assertSame('a.pdfX-Evil: 1', document_clean_file_name("a.pdf\r\nX-Evil: 1"));
        $this->assertSame('a.pdf', document_clean_file_name("a\0.pdf"));
    }

    public function testCleanFileNameNeverReturnsAnEmptyOrDirectoryName(): void
    {
        foreach (['', '   ', '.', '..', '/', "\n"] as $name) {
            $this->assertSame('document', document_clean_file_name($name), var_export($name, true));
        }
    }

    public function testCleanFileNameReplacesInvalidUtf8(): void
    {
        $cleaned = document_clean_file_name("bad\xFFname.pdf");

        $this->assertTrue(mb_check_encoding($cleaned, 'UTF-8'));
        $this->assertStringEndsWith('.pdf', $cleaned);
    }

    public function testCleanFileNameTruncatesLongNamesButKeepsTheExtension(): void
    {
        $cleaned = document_clean_file_name(str_repeat('é', 400) . '.pdf');

        $this->assertSame(255, mb_strlen($cleaned));
        $this->assertStringEndsWith('.pdf', $cleaned);
    }

    public function testContentDispositionKeepsAccentsInTheUtf8Parameter(): void
    {
        $this->assertSame(
            "inline; filename=\"Livret _claireur.pdf\"; filename*=UTF-8''Livret%20%C3%A9claireur.pdf",
            document_content_disposition('inline', 'Livret éclaireur.pdf')
        );
    }

    public function testContentDispositionCannotBeBrokenOutOfItsQuotes(): void
    {
        $header = document_content_disposition('attachment', 'a"; filename="evil.exe');

        $this->assertStringStartsWith('attachment; filename="a_; filename=_evil.exe";', $header);
        $this->assertSame(1, preg_match_all('/filename="/', $header), 'un seul filename= est émis');
    }

    public function testContentDispositionFallsBackToAttachmentForUnknownTypes(): void
    {
        $this->assertStringStartsWith('attachment;', document_content_disposition('whatever', 'a.zip'));
    }

    public function testParseIniSizeUnderstandsPhpShorthand(): void
    {
        $this->assertSame(2 * 1024 * 1024, document_parse_ini_size('2M'));
        $this->assertSame(512 * 1024, document_parse_ini_size('512k'));
        $this->assertSame(1024 ** 3, document_parse_ini_size('1G'));
        $this->assertSame(8388608, document_parse_ini_size('8388608'));
        $this->assertSame(-1, document_parse_ini_size('-1'));
        $this->assertSame(0, document_parse_ini_size(''));
        $this->assertSame(0, document_parse_ini_size('beaucoup'));
    }

    public function testEffectiveUploadLimitIsTheStrictestOfAllLimits(): void
    {
        $mb = 1024 * 1024;

        // La limite de l'application seule.
        $this->assertSame(50 * $mb, document_effective_upload_limit(50 * $mb, 0, 0));
        // upload_max_filesize plus strict que l'application (installation WAMP par défaut).
        $this->assertSame(2 * $mb, document_effective_upload_limit(50 * $mb, 2 * $mb, 8 * $mb));
        // post_max_size doit aussi contenir ce qui accompagne le fichier.
        $this->assertSame(3 * $mb, document_effective_upload_limit(50 * $mb, 100 * $mb, 8 * $mb, 5 * $mb));
        // -1 : illimité, ne limite pas.
        $this->assertSame(50 * $mb, document_effective_upload_limit(50 * $mb, -1, -1, 5 * $mb));
        // Jamais négatif.
        $this->assertSame(0, document_effective_upload_limit(50 * $mb, 100 * $mb, 1 * $mb, 5 * $mb));
    }

    public function testUtcDatesAreShownInParisTime(): void
    {
        // 23h30 UTC le 7 novembre = 0h30 le 8 à Paris (UTC+1 en hiver).
        $this->assertSame('08/11/2026', format_utc_date_fr('2026-11-07 23:30:00'));
        $this->assertSame('07/11/2026', format_utc_date_fr('2026-11-07 12:00:00'));
        // UTC+2 en été.
        $this->assertSame('02/07/2026', format_utc_date_fr('2026-07-01 22:30:00'));
    }

    public function testOnlyTeamLeadersCanManageDocuments(): void
    {
        // État-major : 10 à 20, et 50.
        foreach ([10, 15, 20, 50] as $group_id) {
            $this->assertTrue(can_manage_documents(['user_group_id' => $group_id, 'secondary_group_ids' => []]), "groupe $group_id");
        }

        // QM (8, 9), membres, et scénaristes (groupe secondaire 55) ne gèrent pas les documents.
        foreach ([2, 5, 8, 9, 54] as $group_id) {
            $this->assertFalse(can_manage_documents(['user_group_id' => $group_id, 'secondary_group_ids' => []]), "groupe $group_id");
        }

        $this->assertFalse(can_manage_documents(['user_group_id' => 5, 'secondary_group_ids' => [55]]));
    }
}
