<?php

namespace App\Controllers;

use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\Exceptions\RedirectException;

use App\Models\GalleryModel;
use App\Models\GalleryCategoryModel;

class Upload extends BaseController
{
    private const GALLERY_UPLOAD_PATH = ROOTPATH . '../commandonext/public/pictures/galerie';
    private const GALLERY_UPLOAD_URL = 'https://commandokieffer.com/pictures/galerie';

    public function __construct()
    {
        if (!session('is_logged_in')) {
            $route = empty(uri_string()) ? '/' : uri_string();
            session()->set('after_login_url', $route);
            throw new RedirectException('login');
            exit;
        }
    }

    private function render_message(string $message): string
    {
        return view('generic/head')
            . view('generic/header')
            . view('404', ['message' => $message])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function gallery()
    {
        if (!is_team_leader(session('user'))) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $category_model = model(GalleryCategoryModel::class);

        return view('generic/head')
            . view('generic/header')
            . view('upload_gallery', ['categories' => $category_model->get_all_categories()])
            . view('generic/footer')
            . view('generic/foot');
    }

    public function store_gallery()
    {
        if (!is_team_leader(session('user'))) {
            return $this->render_message("Vous n'avez pas la permission d'accéder à cette page.");
        }

        $category_model = model(GalleryCategoryModel::class);
        $categories = $category_model->get_all_categories();
        $valid_slugs = array_map(fn($category) => $category->slug, $categories);

        $rules = [
            'picture' => 'uploaded[picture]|is_image[picture]|mime_in[picture,image/png,image/jpeg]|max_size[picture,20480]',
            'description' => 'required|max_length[128]',
            'category' => 'required|in_list[' . implode(',', $valid_slugs) . ']',
        ];

        if (!$this->validate($rules)) {
            session()->setFlashdata('errors', $this->validator->getErrors());
            session()->setFlashdata('old', [
                'description' => $_POST['description'] ?? '',
                'category' => $_POST['category'] ?? '',
            ]);
            return redirect()->to('/upload/galerie');
        }

        $picture = $this->request->getFile('picture');
        $extension = $picture->getMimeType() === 'image/png' ? 'png' : 'jpg';
        $filename = $this->generate_uuid() . '.' . $extension;

        try {
            $picture->move(self::GALLERY_UPLOAD_PATH, $filename);
        } catch (HTTPException $e) {
            session()->setFlashdata('errors', ["L'enregistrement de l'image a échoué : " . $e->getMessage()]);
            session()->setFlashdata('old', [
                'description' => $_POST['description'],
                'category' => $_POST['category'],
            ]);
            return redirect()->to('/upload/galerie');
        }

        $gallery_model = model(GalleryModel::class);
        $gallery_model->create_entry(
            $filename,
            $_POST['category'],
            $_POST['description'],
            self::GALLERY_UPLOAD_URL . '/' . $filename
        );

        return redirect('operation_success');
    }

    private function generate_uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
