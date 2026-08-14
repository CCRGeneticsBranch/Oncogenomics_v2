<?php

return [
    /*
    | MCP tools exposed to the LLM in each page scope. Tool names must match
    | the names advertised by the Onco MCP server exactly.
    */
    'scope_tools' => [
        'global' => [
            'getProjects',
            'getCancerTypes',
        ],

        'project' => [
            'expression_by_gene',
            'mutation_by_gene',
            'fusion_by_gene',
            'cnv_by_gene',
            'correlation_by_gene',
            'survival_by_expression',
            'getPCAData',
            'getCorrelationData',
            'getExpGeneSummary',
            'get_fusion_genes',
            'get_pathogeic_mutations',
            'get_project_cnv',
            'get_project_cases',
            'get_project_hla',
            'get_project_patients',
            'get_project_qc',
            'get_project_str',
            'get_project_sample_cases',
            'getCohortSamples',
            'getCohortChIPseq',
            'getCohortMutationGenes',
            'getCohortSchema',
            'runDifferentialExpression',
        ],

        'cancer_type' => [
            'getCohortSamples',
            'getCohortChIPseq',
            'getCohortMutationGenes',
            'getFusionCancerTypeDetail',
        ],
    ],
];
