<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Reports;

use App\Enums\MedicalEntityStatus;
use App\Enums\ReportRiskLevel;
use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

final class ReportAnalysisWebhookRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $isSuccess = fn () => $this->boolean('status');

        return [
            // Correlates this callback to the MedicalReport that was sent for analysis.
            // Required on both success and failure, since `data` is null on failure.
            'original_filename' => ['required', 'string'],
            'status' => ['required', 'boolean'],
            'message' => ['required', 'string'],
            'code' => ['nullable', 'integer'],
            'data' => [Rule::requiredIf($isSuccess), 'nullable', 'array'],

            'data.upload' => ['sometimes', 'array'],
            'data.upload.original_filename' => ['nullable', 'string'],

            'data.report' => [Rule::requiredIf($isSuccess), 'array'],
            'data.report.title' => ['required_with:data.report', 'string', 'max:255'],
            'data.report.report_type' => ['nullable', 'string', 'max:50'],
            'data.report.doctor_name' => ['nullable', 'string', 'max:255'],
            'data.report.hospital_name' => ['nullable', 'string', 'max:255'],
            'data.report.report_date' => ['nullable', 'date'],

            'data.report.profile' => ['nullable', 'array'],
            'data.report.profile.name' => ['nullable', 'string', 'max:255'],
            'data.report.profile.number' => ['nullable', 'string', 'max:30'],
            'data.report.profile.email' => ['nullable', 'email', 'max:255'],
            'data.report.profile.birthdate' => ['nullable', 'string', 'max:30'],
            'data.report.profile.height' => ['nullable', 'string', 'max:30'],
            'data.report.profile.height_unit' => ['nullable', 'string', 'max:10'],
            'data.report.profile.weight' => ['nullable', 'string', 'max:30'],
            'data.report.profile.weight_unit' => ['nullable', 'string', 'max:10'],

            'data.knowledge' => [Rule::requiredIf($isSuccess), 'array'],
            'data.knowledge.summary' => ['nullable', 'string'],
            'data.knowledge.risk_level' => [
                'nullable',
                Rule::in(array_map(fn (ReportRiskLevel $c) => $c->value, ReportRiskLevel::cases())),
            ],
            'data.knowledge.recommendations' => ['nullable', 'array'],
            'data.knowledge.recommendations.*' => ['string'],
            'data.knowledge.confidence_score' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'data.entities' => ['nullable', 'array'],
            'data.entities.*.entity_type' => ['nullable', 'string', 'max:50'],
            'data.entities.*.entity_name' => ['required', 'string', 'max:255'],
            'data.entities.*.value' => ['nullable', 'string', 'max:255'],
            'data.entities.*.unit' => ['nullable', 'string', 'max:50'],
            'data.entities.*.reference_range' => ['nullable', 'string', 'max:100'],
            'data.entities.*.status' => [
                'nullable',
                Rule::in(array_map(fn (MedicalEntityStatus $c) => $c->value, MedicalEntityStatus::cases())),
            ],

            'data.tags' => ['nullable', 'array'],
            'data.tags.*' => ['string', 'max:100'],
        ];
    }
}
