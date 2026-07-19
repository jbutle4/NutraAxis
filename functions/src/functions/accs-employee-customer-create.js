const { app } = require('@azure/functions');
const processRunner = require('../lib/process-runner');

app.http('accs-employee-customer-create', {
  methods: ['POST'],
  authLevel: 'function',
  handler: async (request, context) => {
    let body = {};
    try {
      body = await request.json();
    } catch {
      body = {};
    }

    const params = body.params && typeof body.params === 'object' ? body.params : {};
    context.log('accs-employee-customer-create starting dry_run=%s', Boolean(params.dry_run));

    const result = await processRunner.execute(
      'accs-employee-customer-create',
      {
        dryRun: Boolean(params.dry_run),
        retryFailed: Boolean(params.retry_failed),
        includeExisting: Boolean(params.include_existing),
        fixGroupsOnly: Boolean(params.fix_groups_only),
      }
    );

    context.log(
      'accs-employee-customer-create ok=%s created=%s existing=%s failed=%s',
      result.ok,
      result.result?.created,
      result.result?.existing,
      result.result?.failed
    );

    return {
      status: result.ok ? 200 : 500,
      jsonBody: result,
    };
  },
});
