
import { subjectApi } from "../api/subjectApi.js";
import { offeringApi } from "../api/offeringApi.js";
import { validate, rules, firstError } from "../core/validator.js";
import { ApiError } from "../core/apiError.js";

export const catalogService = {
  async subjects(filters = {}) {
    const { data } = await subjectApi.list(filters);
    return data;
  },

  async saveSubject(values, code = null) {
    const check = validate(values, {
      code: [rules.required],
      name: [rules.required],
      units: [rules.required, rules.numeric],
    });
    if (!check.valid) throw new ApiError(firstError(check.errors), { status: 422, errors: check.errors });

    const payload = { ...values, units: Number(values.units) };
    const { message } = code ? await subjectApi.update(code, payload) : await subjectApi.create(payload);
    return message;
  },

  async removeSubject(code) {
    const { message } = await subjectApi.remove(code);
    return message;
  },

  async offerings(filters = {}) {
    const { data } = await offeringApi.list(filters);
    return data;
  },

  async saveOffering(values) {
    const check = validate(values, {
      subject: [rules.required],
      instructor: [rules.required],
      slots: [rules.required, rules.numeric],
    });
    if (!check.valid) throw new ApiError(firstError(check.errors), { status: 422, errors: check.errors });

    const { message } = await offeringApi.create({ ...values, slots: Number(values.slots) });
    return message;
  },
};

export default catalogService;
