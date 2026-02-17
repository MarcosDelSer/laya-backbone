<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\ServiceAgreement\Domain\ServiceAgreementGateway;
use Gibbon\Module\ServiceAgreement\Domain\AnnexGateway;
use Gibbon\Module\ServiceAgreement\Domain\SignatureGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Service Agreements'), 'serviceAgreement.php');
$page->breadcrumbs->add(__('Sign Agreement'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/ServiceAgreement/serviceAgreement_sign.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get agreement ID from request
    $gibbonServiceAgreementID = $_GET['gibbonServiceAgreementID'] ?? '';

    if (empty($gibbonServiceAgreementID)) {
        $page->addError(__('No service agreement specified.'));
        return;
    }

    // Get gateways via DI container
    $serviceAgreementGateway = $container->get(ServiceAgreementGateway::class);
    $annexGateway = $container->get(AnnexGateway::class);
    $signatureGateway = $container->get(SignatureGateway::class);

    // Get agreement with details
    $agreement = $serviceAgreementGateway->getAgreementWithDetails($gibbonServiceAgreementID);

    if (!$agreement) {
        $page->addError(__('The specified service agreement could not be found.'));
        return;
    }

    // Check if agreement is in a signable state
    if (!in_array($agreement['status'], ['Pending Signature', 'Active'])) {
        $page->addWarning(__('This agreement is not currently available for signing. Status: {status}', ['status' => $agreement['status']]));
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_view.php&gibbonServiceAgreementID=' . $gibbonServiceAgreementID . '" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">' . __('View Agreement') . '</a>';
        return;
    }

    // Get current user info
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get existing signatures
    $signatures = $signatureGateway->selectSignaturesByAgreement($gibbonServiceAgreementID)->fetchAll();
    $existingSignerTypes = array_column($signatures, 'signerType');

    // Check which signatures are still needed
    $hasParentSignature = in_array('Parent', $existingSignerTypes);
    $hasProviderSignature = in_array('Provider', $existingSignerTypes);
    $allSignaturesComplete = $hasParentSignature && $hasProviderSignature;

    // Get annexes
    $annexes = $annexGateway->selectAnnexesByAgreement($gibbonServiceAgreementID)->fetchAll();
    $annexTypeNames = AnnexGateway::getAnnexTypeNames();

    // Page header
    echo '<div class="flex justify-between items-start mb-6">';
    echo '<div>';
    echo '<h2 class="mb-2">' . __('Sign Service Agreement') . '</h2>';
    echo '<p class="text-gray-600">' . __('Agreement #') . ': <strong>' . htmlspecialchars($agreement['agreementNumber']) . '</strong></p>';
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_view.php&gibbonServiceAgreementID=' . $gibbonServiceAgreementID . '" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">&larr; ' . __('Back to Agreement') . '</a>';
    echo '</div>';

    // All signatures complete message
    if ($allSignaturesComplete) {
        echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">';
        echo '<strong>' . __('All Required Signatures Complete') . '</strong>';
        echo '<p class="mt-1">' . __('Both parent and provider signatures have been collected for this agreement.') . '</p>';
        echo '<a href="' . $session->get('absoluteURL') . '/modules/ServiceAgreement/serviceAgreement_pdf.php?gibbonServiceAgreementID=' . $gibbonServiceAgreementID . '" class="inline-block mt-2 bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600" target="_blank">' . __('Download Signed PDF') . '</a>';
        echo '</div>';
    }

    // ========================================
    // Agreement Summary for Review
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Agreement Summary') . '</h3>';

    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">';

    // Child Info
    echo '<div class="bg-blue-50 rounded p-3">';
    echo '<span class="text-gray-500 text-sm">' . __('Child') . '</span>';
    echo '<div class="font-medium">' . htmlspecialchars($agreement['childName']) . '</div>';
    if (!empty($agreement['childDateOfBirth'])) {
        echo '<div class="text-sm text-gray-600">' . __('DOB') . ': ' . Format::date($agreement['childDateOfBirth']) . '</div>';
    }
    echo '</div>';

    // Parent Info
    echo '<div class="bg-green-50 rounded p-3">';
    echo '<span class="text-gray-500 text-sm">' . __('Parent/Guardian') . '</span>';
    echo '<div class="font-medium">' . htmlspecialchars($agreement['parentName']) . '</div>';
    if (!empty($agreement['parentEmail'])) {
        echo '<div class="text-sm text-gray-600">' . htmlspecialchars($agreement['parentEmail']) . '</div>';
    }
    echo '</div>';

    // Provider Info
    echo '<div class="bg-gray-50 rounded p-3">';
    echo '<span class="text-gray-500 text-sm">' . __('Provider') . '</span>';
    echo '<div class="font-medium">' . htmlspecialchars($agreement['providerName']) . '</div>';
    echo '</div>';

    // Dates
    echo '<div>';
    echo '<span class="text-gray-500 text-sm">' . __('Effective Date') . '</span>';
    echo '<div class="font-medium">' . (!empty($agreement['effectiveDate']) ? Format::date($agreement['effectiveDate']) : '-') . '</div>';
    echo '</div>';

    echo '<div>';
    echo '<span class="text-gray-500 text-sm">' . __('Expiration Date') . '</span>';
    echo '<div class="font-medium">' . (!empty($agreement['expirationDate']) ? Format::date($agreement['expirationDate']) : __('No expiration')) . '</div>';
    echo '</div>';

    // Contribution
    echo '<div>';
    echo '<span class="text-gray-500 text-sm">' . __('Daily Contribution') . '</span>';
    echo '<div class="font-medium">$' . number_format($agreement['dailyReducedContribution'] ?? 9.35, 2) . '</div>';
    echo '</div>';

    echo '</div>'; // End grid

    // View full agreement link
    echo '<div class="text-center">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_view.php&gibbonServiceAgreementID=' . $gibbonServiceAgreementID . '" class="text-blue-600 hover:underline">' . __('View Full Agreement Details') . ' &rarr;</a>';
    echo '</div>';

    echo '</div>'; // End agreement summary

    // ========================================
    // Quebec Consumer Protection Act Notice
    // ========================================
    echo '<div class="bg-yellow-50 border border-yellow-300 rounded-lg p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold text-yellow-800 mb-3">' . __('Quebec Consumer Protection Act Notice') . '</h3>';

    echo '<div class="text-yellow-900 space-y-2">';
    echo '<p><strong>' . __('Important Legal Notice (Loi sur la protection du consommateur):') . '</strong></p>';
    echo '<ul class="list-disc list-inside ml-4 space-y-1">';
    echo '<li>' . __('You have 10 days to cancel this contract without penalty after receiving the signed copy.') . '</li>';
    echo '<li>' . __('All fees and charges are clearly stated in this agreement.') . '</li>';
    echo '<li>' . __('The provider must give written notice before any fee increases.') . '</li>';
    echo '<li>' . __('This agreement is subject to the Quebec Consumer Protection Act.') . '</li>';
    echo '</ul>';
    echo '</div>';

    // Consumer Protection acknowledgment status
    if ($agreement['consumerProtectionAcknowledged'] === 'Y') {
        echo '<div class="mt-4 text-green-700 bg-green-100 rounded p-2">';
        echo '<span class="mr-1">&#10003;</span>' . __('Consumer Protection Act has been acknowledged');
        if (!empty($agreement['consumerProtectionAcknowledgedDate'])) {
            echo ' ' . __('on') . ' ' . Format::dateTime($agreement['consumerProtectionAcknowledgedDate']);
        }
        echo '</div>';
    }

    echo '</div>'; // End Consumer Protection notice

    // ========================================
    // Annexes Summary
    // ========================================
    if (!empty($annexes)) {
        echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Annexes Included in This Agreement') . '</h3>';

        echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';

        foreach ($annexes as $annex) {
            if ($annex['status'] === 'NotApplicable') continue;

            $annexType = $annex['annexType'];
            $annexName = $annexTypeNames[$annexType] ?? __('Annex') . ' ' . $annexType;

            $statusBadge = '';
            switch ($annex['status']) {
                case 'Signed':
                    $statusBadge = '<span class="bg-green-200 text-green-800 px-2 py-1 rounded text-xs">' . __('Signed') . '</span>';
                    break;
                case 'Pending':
                    $statusBadge = '<span class="bg-orange-200 text-orange-800 px-2 py-1 rounded text-xs">' . __('Pending') . '</span>';
                    break;
                case 'Declined':
                    $statusBadge = '<span class="bg-red-200 text-red-800 px-2 py-1 rounded text-xs">' . __('Declined') . '</span>';
                    break;
            }

            echo '<div class="border rounded p-3">';
            echo '<div class="flex justify-between items-center mb-2">';
            echo '<span class="font-medium">' . __('Annex') . ' ' . $annexType . ': ' . __($annexName) . '</span>';
            echo $statusBadge;
            echo '</div>';

            // Brief description based on type
            switch ($annexType) {
                case 'A':
                    echo '<p class="text-sm text-gray-600">' . __('Field trips authorization') . ': ';
                    echo ($annex['fieldTripsAuthorized'] === 'Y') ? __('Authorized') : __('Not Authorized');
                    echo '</p>';
                    break;
                case 'B':
                    echo '<p class="text-sm text-gray-600">' . __('Hygiene items') . ': ';
                    echo ($annex['hygieneItemsIncluded'] === 'Y') ? __('Included') : __('Not Included');
                    if (!empty($annex['hygieneItemsMonthlyFee'])) {
                        echo ' ($' . number_format($annex['hygieneItemsMonthlyFee'], 2) . '/month)';
                    }
                    echo '</p>';
                    break;
                case 'C':
                    echo '<p class="text-sm text-gray-600">' . __('Supplementary meals') . ': ';
                    echo ($annex['supplementaryMealsIncluded'] === 'Y') ? __('Included') : __('Not Included');
                    if (!empty($annex['supplementaryMealsFee'])) {
                        echo ' ($' . number_format($annex['supplementaryMealsFee'], 2) . ')';
                    }
                    echo '</p>';
                    break;
                case 'D':
                    echo '<p class="text-sm text-gray-600">' . __('Extended hours') . ': ';
                    echo ($annex['extendedHoursIncluded'] === 'Y') ? __('Included') : __('Not Included');
                    if (!empty($annex['extendedHoursHourlyRate'])) {
                        echo ' ($' . number_format($annex['extendedHoursHourlyRate'], 2) . '/hour)';
                    }
                    echo '</p>';
                    break;
            }

            echo '</div>';
        }

        echo '</div>'; // End grid
        echo '</div>'; // End annexes section
    }

    // ========================================
    // Existing Signatures
    // ========================================
    if (!empty($signatures)) {
        echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Collected Signatures') . '</h3>';

        echo '<table class="w-full border-collapse">';
        echo '<thead>';
        echo '<tr class="bg-gray-100">';
        echo '<th class="border px-4 py-2 text-left">' . __('Signer Type') . '</th>';
        echo '<th class="border px-4 py-2 text-left">' . __('Name') . '</th>';
        echo '<th class="border px-4 py-2 text-left">' . __('Date/Time') . '</th>';
        echo '<th class="border px-4 py-2 text-left">' . __('Status') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($signatures as $signature) {
            echo '<tr>';
            echo '<td class="border px-4 py-2">' . __($signature['signerType']) . '</td>';
            echo '<td class="border px-4 py-2">' . htmlspecialchars($signature['signerName']) . '</td>';
            echo '<td class="border px-4 py-2">' . (!empty($signature['signedDate']) ? Format::dateTime($signature['signedDate']) : '-') . '</td>';
            echo '<td class="border px-4 py-2">';
            if ($signature['verified'] === 'Y') {
                echo '<span class="text-green-600">&#10003; ' . __('Verified') . '</span>';
            } else {
                echo '<span class="text-blue-600">' . __('Collected') . '</span>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    // ========================================
    // Signature Collection Form
    // ========================================
    if (!$allSignaturesComplete) {
        echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Collect Signature') . '</h3>';

        // Show which signatures are needed
        echo '<div class="mb-4 p-3 bg-blue-50 rounded">';
        echo '<strong>' . __('Signatures Needed:') . '</strong>';
        echo '<ul class="list-disc list-inside mt-1">';
        if (!$hasParentSignature) {
            echo '<li>' . __('Parent/Guardian Signature') . '</li>';
        }
        if (!$hasProviderSignature) {
            echo '<li>' . __('Provider Signature') . '</li>';
        }
        echo '</ul>';
        echo '</div>';

        // Signature form
        $form = Form::create('signAgreement', $session->get('absoluteURL') . '/modules/ServiceAgreement/serviceAgreement_signProcess.php');
        $form->setMethod('post');
        $form->addHiddenValue('gibbonServiceAgreementID', $gibbonServiceAgreementID);
        $form->addHiddenValue('address', $session->get('address'));

        // Signer Type dropdown (only show options that haven't been signed)
        $signerTypeOptions = [];
        if (!$hasParentSignature) {
            $signerTypeOptions['Parent'] = __('Parent/Guardian');
        }
        if (!$hasProviderSignature) {
            $signerTypeOptions['Provider'] = __('Provider');
        }

        $row = $form->addRow();
        $row->addLabel('signerType', __('Signer Type'));
        $row->addSelect('signerType')
            ->fromArray($signerTypeOptions)
            ->required()
            ->placeholder(__('Select Signer Type'));

        // Signer Name
        $row = $form->addRow();
        $row->addLabel('signerName', __('Full Legal Name'));
        $row->addTextField('signerName')
            ->required()
            ->maxLength(255)
            ->setValue($agreement['parentName'])
            ->placeholder(__('Enter your full legal name as it appears on official documents'));

        // Signer Email
        $row = $form->addRow();
        $row->addLabel('signerEmail', __('Email Address'));
        $row->addEmail('signerEmail')
            ->maxLength(255)
            ->setValue($agreement['parentEmail'] ?? '')
            ->placeholder(__('Email for confirmation'));

        // Signature Type
        $row = $form->addRow();
        $row->addLabel('signatureType', __('Signature Method'));
        $row->addSelect('signatureType')
            ->fromArray([
                'Typed' => __('Typed Signature'),
                'Drawn' => __('Drawn Signature'),
            ])
            ->required()
            ->selected('Typed');

        // Typed Signature (shown by default)
        $row = $form->addRow()->addClass('signatureTyped');
        $row->addLabel('typedSignature', __('Type Your Signature'));
        $row->addTextField('typedSignature')
            ->maxLength(255)
            ->placeholder(__('Type your full name as signature'));

        // Drawn Signature Canvas (hidden by default, shown via JS)
        $row = $form->addRow()->addClass('signatureDrawn hidden');
        $row->addLabel('signatureCanvas', __('Draw Your Signature'));
        $row->addContent('
            <div class="border rounded p-2 bg-white">
                <canvas id="signatureCanvas" width="500" height="150" class="border border-gray-300 rounded cursor-crosshair" style="touch-action: none;"></canvas>
                <div class="mt-2">
                    <button type="button" onclick="clearSignature()" class="bg-gray-200 text-gray-700 px-3 py-1 rounded text-sm hover:bg-gray-300">' . __('Clear') . '</button>
                </div>
            </div>
            <input type="hidden" name="signatureData" id="signatureData" value="">
        ');

        // Consumer Protection Acknowledgment
        $row = $form->addRow();
        $row->addLabel('consumerProtectionAcknowledged', __('Consumer Protection Act'));
        $row->addCheckbox('consumerProtectionAcknowledged')
            ->description(__('I acknowledge that I have read and understand the Quebec Consumer Protection Act notice above.'))
            ->required();

        // Terms Acceptance
        $row = $form->addRow();
        $row->addLabel('termsAccepted', __('Accept Terms'));
        $row->addCheckbox('termsAccepted')
            ->description(__('I have read and agree to all terms and conditions of this service agreement.'))
            ->required();

        // Legal Acknowledgment
        $row = $form->addRow();
        $row->addLabel('legalAcknowledgment', __('Legal Acknowledgment'));
        $row->addCheckbox('legalAcknowledgment')
            ->description(__('I understand that my electronic signature is legally binding and has the same legal effect as a handwritten signature.'))
            ->required();

        $row = $form->addRow();
        $row->addSubmit(__('Sign Agreement'));

        echo $form->getOutput();

        // JavaScript for signature handling
        echo '<script>
            // Toggle signature input based on type
            document.querySelector("[name=signatureType]").addEventListener("change", function() {
                var isDrawn = this.value === "Drawn";
                document.querySelectorAll(".signatureTyped").forEach(function(el) {
                    el.classList.toggle("hidden", isDrawn);
                });
                document.querySelectorAll(".signatureDrawn").forEach(function(el) {
                    el.classList.toggle("hidden", !isDrawn);
                });
                if (isDrawn) {
                    initSignatureCanvas();
                }
            });

            // Signature canvas handling
            var canvas, ctx, isDrawing = false;

            function initSignatureCanvas() {
                canvas = document.getElementById("signatureCanvas");
                if (!canvas) return;
                ctx = canvas.getContext("2d");
                ctx.strokeStyle = "#000";
                ctx.lineWidth = 2;
                ctx.lineCap = "round";
                ctx.lineJoin = "round";

                // Mouse events
                canvas.addEventListener("mousedown", startDrawing);
                canvas.addEventListener("mousemove", draw);
                canvas.addEventListener("mouseup", stopDrawing);
                canvas.addEventListener("mouseout", stopDrawing);

                // Touch events
                canvas.addEventListener("touchstart", function(e) {
                    e.preventDefault();
                    var touch = e.touches[0];
                    var mouseEvent = new MouseEvent("mousedown", {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                    canvas.dispatchEvent(mouseEvent);
                });
                canvas.addEventListener("touchmove", function(e) {
                    e.preventDefault();
                    var touch = e.touches[0];
                    var mouseEvent = new MouseEvent("mousemove", {
                        clientX: touch.clientX,
                        clientY: touch.clientY
                    });
                    canvas.dispatchEvent(mouseEvent);
                });
                canvas.addEventListener("touchend", function(e) {
                    var mouseEvent = new MouseEvent("mouseup", {});
                    canvas.dispatchEvent(mouseEvent);
                });
            }

            function startDrawing(e) {
                isDrawing = true;
                draw(e);
            }

            function draw(e) {
                if (!isDrawing) return;
                var rect = canvas.getBoundingClientRect();
                var x = e.clientX - rect.left;
                var y = e.clientY - rect.top;
                ctx.lineTo(x, y);
                ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(x, y);
            }

            function stopDrawing() {
                isDrawing = false;
                ctx.beginPath();
                // Save signature data
                document.getElementById("signatureData").value = canvas.toDataURL("image/png");
            }

            function clearSignature() {
                if (!canvas || !ctx) return;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                document.getElementById("signatureData").value = "";
            }

            // Form validation
            document.querySelector("form").addEventListener("submit", function(e) {
                var signatureType = document.querySelector("[name=signatureType]").value;
                if (signatureType === "Typed") {
                    var typedSig = document.querySelector("[name=typedSignature]").value.trim();
                    if (!typedSig) {
                        alert("' . __('Please type your signature.') . '");
                        e.preventDefault();
                        return false;
                    }
                } else if (signatureType === "Drawn") {
                    var signatureData = document.getElementById("signatureData").value;
                    if (!signatureData) {
                        alert("' . __('Please draw your signature on the canvas.') . '");
                        e.preventDefault();
                        return false;
                    }
                }
            });
        </script>';

        echo '</div>'; // End signature form section
    }

    // ========================================
    // Signature Status Summary
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Signature Status') . '</h3>';

    echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">';

    // Parent Status
    echo '<div class="p-4 rounded ' . ($hasParentSignature ? 'bg-green-100' : 'bg-orange-100') . '">';
    echo '<div class="text-3xl mb-2">' . ($hasParentSignature ? '&#10003;' : '&#x25CB;') . '</div>';
    echo '<div class="font-medium">' . __('Parent/Guardian') . '</div>';
    echo '<div class="text-sm text-gray-600">' . ($hasParentSignature ? __('Signed') : __('Pending')) . '</div>';
    echo '</div>';

    // Provider Status
    echo '<div class="p-4 rounded ' . ($hasProviderSignature ? 'bg-green-100' : 'bg-orange-100') . '">';
    echo '<div class="text-3xl mb-2">' . ($hasProviderSignature ? '&#10003;' : '&#x25CB;') . '</div>';
    echo '<div class="font-medium">' . __('Provider') . '</div>';
    echo '<div class="text-sm text-gray-600">' . ($hasProviderSignature ? __('Signed') : __('Pending')) . '</div>';
    echo '</div>';

    // Overall Status
    echo '<div class="p-4 rounded ' . ($allSignaturesComplete ? 'bg-green-100' : 'bg-blue-100') . '">';
    echo '<div class="text-3xl mb-2">' . ($allSignaturesComplete ? '&#10003;' : '&#8987;') . '</div>';
    echo '<div class="font-medium">' . __('Agreement Status') . '</div>';
    echo '<div class="text-sm text-gray-600">' . ($allSignaturesComplete ? __('Complete') : __('In Progress')) . '</div>';
    echo '</div>';

    echo '</div>'; // End status grid
    echo '</div>'; // End status section
}
