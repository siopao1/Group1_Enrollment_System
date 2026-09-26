
import { settingsApi } from "../api/settingsApi.js";

export const settingsService = {
  async get() {
    const { data } = await settingsApi.get();
    return data;
  },

  async update(values) {
    const { data, message } = await settingsApi.update(values);
    return { settings: data, message };
  },
};

export default settingsService;
