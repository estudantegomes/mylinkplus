<?php

namespace App\Controllers;

use App\Models\Folder;
use App\Models\OrganizationItem;
use Core\Exceptions\HTTPException;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\Csrf;
use Lib\FlashMessage;
use Lib\Paginator;
use PDOException;

abstract class OrganizationController extends Controller
{
    protected string $layout = 'organization';

    /** @var class-string<OrganizationItem> */
    protected string $model;
    protected string $resource;
    protected string $label;

    public function index(Request $request): void
    {
        $conditions = ['user_id' => $this->userId()];
        $parent = null;

        if ($this->model === Folder::class) {
            $parentId = $request->getParam('parent_id');
            $parent = $parentId === null || $parentId === '' ? null : $this->owned($parentId);
            $conditions['parent_id'] = $parent?->id;
        }

        $page = filter_var($request->getParam('page', 1), FILTER_VALIDATE_INT) ?: 1;
        $paginator = new Paginator(
            class: $this->model,
            page: $page,
            per_page: 10,
            table: $this->model::table(),
            attributes: $this->model::columns(),
            conditions: $conditions
        );

        $this->view('index', compact('paginator', 'parent'));
    }

    public function new(Request $request): void
    {
        $item = new $this->model(['user_id' => $this->userId()]);
        $parentId = $request->getParam('parent_id');

        if ($item instanceof Folder && $parentId !== null && $parentId !== '') {
            $item->parent_id = $this->owned($parentId)->id;
        }

        $this->form($item);
    }

    public function create(Request $request): void
    {
        Csrf::validate($request);
        $item = new $this->model(['user_id' => $this->userId()]);
        $this->fill($item, $request);
        $this->persist($item);
    }

    public function show(Request $request): void
    {
        $this->view('show', ['item' => $this->owned($request->getParam('id'))]);
    }

    public function edit(Request $request): void
    {
        $this->form($this->owned($request->getParam('id')));
    }

    public function update(Request $request): void
    {
        Csrf::validate($request);
        $item = $this->owned($request->getParam('id'));
        $this->fill($item, $request);
        $this->persist($item);
    }

    public function destroy(Request $request): void
    {
        Csrf::validate($request);
        $item = $this->owned($request->getParam('id'));

        if (!$item->destroy()) {
            FlashMessage::danger($item->errors('base') ?? 'Não foi possível excluir o registro.');
            $this->redirectTo('/' . $this->resource . '/' . $item->id);
            return;
        }

        FlashMessage::success('Registro excluído.');
        $query = $item instanceof Folder && $item->parent_id !== null
            ? '?parent_id=' . $item->parent_id : '';
        $this->redirectTo('/' . $this->resource . $query);
    }

    private function userId(): int
    {
        $user = $this->currentUser();
        if ($user === null || !$user->isActive()) {
            throw new HTTPException('Autenticacao necessaria.', 401);
        }
        return $user->id;
    }

    private function owned(mixed $id): OrganizationItem
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $item = $id === false ? null : $this->model::findBy(['id' => $id, 'user_id' => $this->userId()]);

        if ($item === null) {
            throw new HTTPException('Registro nao encontrado.', 404);
        }
        return $item;
    }

    private function fill(OrganizationItem $item, Request $request): void
    {
        foreach (['name', 'color'] as $field) {
            $value = $request->getParam($field, '');
            $item->$field = is_string($value) ? trim($value) : '';
        }
        $item->color = $item->color === '' ? null : $item->color;

        if ($item instanceof Folder) {
            $parent = $request->getParam('parent_id');
            $item->parent_id = $parent === null || $parent === '' ? null
                : (filter_var($parent, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: -1);
        }
    }

    private function persist(OrganizationItem $item): void
    {
        try {
            if ($item->save()) {
                FlashMessage::success('Registro salvo.');
                $this->redirectTo('/' . $this->resource . '/' . $item->id);
                return;
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }
            $item->addError('name', 'Já existe um registro com este nome.');
        }

        http_response_code(422);
        $this->form($item);
    }

    private function form(OrganizationItem $item): void
    {
        $parents = $item instanceof Folder ? Folder::where(['user_id' => $this->userId()]) : [];
        $this->view('form', compact('item', 'parents'));
    }

    /** @param array<string, mixed> $data */
    private function view(string $view, array $data): void
    {
        header('Cache-Control: no-store');
        $this->render('organization/' . $view, array_merge($data, [
            'title' => $this->label . ' | MyLink+',
            'resource' => $this->resource,
            'label' => $this->label,
        ]));
    }
}
