<?php

namespace Botble\Faq\Http\Controllers;

use Botble\Base\Events\CreatedContentEvent;
use Botble\Base\Events\DeletedContentEvent;
use Botble\Base\Events\UpdatedContentEvent;
use Botble\Base\Facades\PageTitle;
use Botble\Base\Forms\FormBuilder;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Faq\Forms\FaqCategoryForm;
use Botble\Faq\Http\Requests\FaqCategoryRequest;
use Botble\Faq\Models\FaqCategory;
use Botble\Faq\Repositories\Interfaces\FaqCategoryInterface;
use Botble\Faq\Tables\FaqCategoryTable;
use Exception;
use Illuminate\Http\Request;

class FaqCategoryController extends BaseController
{
    public function __construct(protected FaqCategoryInterface $faqCategoryRepository)
    {
    }

    public function index(FaqCategoryTable $table)
    {
        PageTitle::setTitle(trans('plugins/faq::faq-category.name'));

        return $table->renderTable();
    }

    public function create(FormBuilder $formBuilder)
    {
        PageTitle::setTitle(trans('plugins/faq::faq-category.create'));

        return $formBuilder->create(FaqCategoryForm::class)->renderForm();
    }

    public function store(FaqCategoryRequest $request, BaseHttpResponse $response)
    {
        $faqCategory = $this->faqCategoryRepository->createOrUpdate($request->input());

        event(new CreatedContentEvent(FAQ_CATEGORY_MODULE_SCREEN_NAME, $request, $faqCategory));

        return $response
            ->setPreviousUrl(route('faq_category.index'))
            ->setNextUrl(route('faq_category.edit', $faqCategory->id))
            ->setMessage(trans('core/base::notices.create_success_message'));
    }

    public function edit(FaqCategory $faqCategory, FormBuilder $formBuilder)
    {
        PageTitle::setTitle(trans('core/base::forms.edit_item', ['name' => $faqCategory->name]));

        return $formBuilder->create(FaqCategoryForm::class, ['model' => $faqCategory])->renderForm();
    }

    public function update(FaqCategory $faqCategory, FaqCategoryRequest $request, BaseHttpResponse $response)
    {
        $faqCategory->fill($request->input());

        $this->faqCategoryRepository->createOrUpdate($faqCategory);

        event(new UpdatedContentEvent(FAQ_CATEGORY_MODULE_SCREEN_NAME, $request, $faqCategory));

        return $response
            ->setPreviousUrl(route('faq_category.index'))
            ->setMessage(trans('core/base::notices.update_success_message'));
    }

    public function destroy(FaqCategory $faqCategory, Request $request, BaseHttpResponse $response)
    {
        try {
            $this->faqCategoryRepository->delete($faqCategory);

            event(new DeletedContentEvent(FAQ_CATEGORY_MODULE_SCREEN_NAME, $request, $faqCategory));

            return $response->setMessage(trans('core/base::notices.delete_success_message'));
        } catch (Exception $exception) {
            return $response
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function deletes(Request $request, BaseHttpResponse $response)
    {
        $ids = $request->input('ids');
        if (empty($ids)) {
            return $response
                ->setError()
                ->setMessage(trans('core/base::notices.no_select'));
        }

        foreach ($ids as $id) {
            $faqCategory = $this->faqCategoryRepository->findOrFail($id);
            $this->faqCategoryRepository->delete($faqCategory);
            event(new DeletedContentEvent(FAQ_CATEGORY_MODULE_SCREEN_NAME, $request, $faqCategory));
        }

        return $response->setMessage(trans('core/base::notices.delete_success_message'));
    }
}
