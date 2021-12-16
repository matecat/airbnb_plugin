/**
 * Add item to glossary
 *
 * @param {string} idSegment
 * @param {string} jwt
 * @returns {Promise<object>}
 */
export const segmentDelivery = async (idSegment, jwt) => {
  const dataParams = {
    id_segment: idSegment,
    jwt: jwt,
  }
  const formData = new FormData()

  Object.keys(dataParams).forEach((key) => {
    formData.append(key, dataParams[key])
  })
  const response = await fetch(
    `/plugins/airbnb/job/${config.id_job}/${config.password}/segment_delivery`,
    {
      method: 'POST',
      credentials: 'include',
      body: formData,
    },
  )

  if (!response.ok) return Promise.reject(response)

  const {errors, ...data} = await response.json()
  if (errors && errors.length > 0) return Promise.reject(errors)

  return data
}
