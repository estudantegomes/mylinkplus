<?php

namespace App\Controllers;

use App\Models\Folder;
use Core\Database\Database;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use PDO;

class FolderController extends Controller
{
    public function index(Request $request): void
    {
        $userId = $this->currentUser()->id;
        $parentId = $request->getParam('parent_id');
        
        $pdo = Database::getDatabaseConn();
        
        // Se houver parent_id, lista as subpastas; caso contrário, lista as pastas raiz.
        if ($parentId) {
            $sql = 'SELECT * FROM folders WHERE user_id = :user_id AND parent_id = :parent_id ORDER BY name ASC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'parent_id' => $parentId]);
        } else {
            $sql = 'SELECT * FROM folders WHERE user_id = :user_id AND parent_id IS NULL ORDER BY name ASC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
        }
        
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = array_map(fn($f) => [
            'id' => $f['id'],
            'name' => $f['name'],
            'color' => $f['color'],
            'parent_id' => $f['parent_id']
        ], $folders);

        $this->jsonResponse($data);
    }

    public function store(Request $request): void
    {
        $folder = new Folder([
            'user_id' => $this->currentUser()->id,
            'parent_id' => $request->getParam('parent_id') ?: null,
            'name' => $request->getParam('name'),
            'color' => $request->getParam('color')
        ]);

        if ($folder->save()) {
            $this->jsonResponse(['success' => true, 'id' => $folder->id], 201);
        } else {
            $this->jsonResponse(['errors' => $folder->errors()], 422);
        }
    }

    public function update(Request $request): void
    {
        $id = (int) $request->getParam('id');
        $folder = Folder::findById($id);
        
        if ($folder && $folder->user_id === $this->currentUser()->id) {
            // Como é um PUT, lemos os dados brutos (raw) caso a classe Request não os encontre
            parse_str(file_get_contents("php://input"), $putData);
            
            // Tenta pegar do Request (se POST), se não, pega do $putData, se não mantém o atual
            $folder->name = $request->getParam('name') ?? $putData['name'] ?? $folder->name;
            $folder->color = $request->getParam('color') ?? $putData['color'] ?? $folder->color;
            
            if ($folder->save()) {
                $this->jsonResponse(['success' => true]);
            } else {
                $this->jsonResponse(['errors' => $folder->errors()], 422);
            }
        } else {
            $this->jsonResponse(['error' => 'Não autorizado ou não encontrado'], 403);
        }
    }

    public function destroy(Request $request): void
    {
        $folder = Folder::findById((int) $request->getParam('id'));
        
        if ($folder && $folder->user_id === $this->currentUser()->id) {
            $folder->destroy();
            $this->jsonResponse(['success' => true]);
        } else {
            $this->jsonResponse(['error' => 'Não autorizado ou não encontrado'], 403);
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}