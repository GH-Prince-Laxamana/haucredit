<?php
// requirements_map for seed_configurations.php
/* ================= REQUIREMENTS MAP ================= */
return [
  'OSA-Initiated Activity' => [
    'On-campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary'],
    'Virtual Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary'],
    'Community Service - On-campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'Student Organization Intake Form (OCES Annex A Form)'],
    'Community Service - Virtual Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'Student Organization Intake Form (OCES Annex A Form)'],
    'Off-Campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'Student Organization Intake Form (OCES Annex A Form)'],
    'Community Service - Off-campus Activity' => ['Approval Letter from Dean', 'Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'Student Organization Intake Form (OCES Annex A Form)'],
  ],
  'Student-Initiated Activity' => [
    'On-campus Activity' => ['Program Flow and/or Itinerary', 'Planned Budget'],
    'Virtual Activity' => ['Program Flow and/or Itinerary', 'Planned Budget'],
    'Community Service - On-campus Activity' => ['Program Flow and/or Itinerary', 'Planned Budget', 'Student Organization Intake Form (OCES Annex A Form)'],
    'Community Service - Virtual Activity' => ['Program Flow and/or Itinerary', 'Planned Budget', 'Student Organization Intake Form (OCES Annex A Form)'],
    'Off-Campus Activity' => ['Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'Student Organization Intake Form (OCES Annex A Form)'],
    'Community Service - Off-campus Activity' => ['Program Flow and/or Itinerary', 'Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'Student Organization Intake Form (OCES Annex A Form)'],
  ],
  'Participation' => [
    'On-campus Activity' => [],
    'Virtual Activity' => [],
    'Community Service - On-campus Activity' => ['Student Organization Intake Form (OCES Annex A Form)'],
    'Community Service - Virtual Activity' => ['Student Organization Intake Form (OCES Annex A Form)'],
    'Off-Campus Activity' => ['Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance'],
    'Community Service - Off-campus Activity' => ['Parental Consent', 'Letter of Undertaking', 'Planned Budget', 'List of Participants', 'CHEd Certificate of Compliance', 'Student Organization Intake Form (OCES Annex A Form)'],
  ]
];
?>